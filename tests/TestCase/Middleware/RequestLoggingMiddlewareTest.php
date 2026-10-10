<?php
declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Error\ApiExceptionRenderer;
use App\Error\SafeErrorLogger;
use App\Log\Engine\SafeFileLog;
use App\Log\RequestLogContext;
use App\Middleware\RequestLoggingMiddleware;
use Cake\Core\Configure;
use Cake\Error\ErrorLogger;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Error\PhpError;
use Cake\Http\MiddlewareQueue;
use Cake\Http\Response;
use Cake\Http\Runner;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Stringable;

class RequestLoggingMiddlewareTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $logConfig;

    private bool $debug;

    private string $directory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->debug = (bool)Configure::read('debug');
        Configure::write('debug', false);
        $this->logConfig = [];
        foreach (Log::configured() as $name) {
            $this->logConfig[$name] = Log::getConfig($name);
        }
        Log::reset();
        $this->directory = ROOT . '/logs/logging-test-' . bin2hex(random_bytes(8)) . '/';
        $config = require CONFIG . 'app.php';
        foreach ($config['Log'] as $name => $engine) {
            $engine['path'] = $this->directory;
            Log::setConfig($name, $engine);
        }
        RequestLogContext::set(null);
        Router::reload();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Log::reset();
        Log::setConfig($this->logConfig);
        RequestLogContext::set(null);
        Router::reload();
        Configure::write('debug', $this->debug);
        foreach (glob($this->directory . '*') ?: [] as $file) {
            if (is_dir($file)) {
                rmdir($file);
            } else {
                unlink($file);
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function debugModes(): array
    {
        return [
            'production' => [false],
            'debug' => [true],
        ];
    }

    /**
     * The real Cake error handler preserves the existing renderer's exact bytes.
     */
    #[DataProvider('debugModes')]
    public function testUnhandledExceptionIsCorrelatedWithoutChangingResponse(bool $debug): void
    {
        Configure::write('debug', $debug);
        $request = $this->sensitiveRequest();
        $exception = new RuntimeException('password-secret token-secret session-secret title-secret body-secret');
        $config = require CONFIG . 'app.php';
        $baseline = (new ErrorHandlerMiddleware([
            'exceptionRenderer' => ApiExceptionRenderer::class,
            'logger' => ErrorLogger::class,
            'log' => false,
        ]))->process($request, $this->throwingHandler($exception));

        $capturedId = null;
        $queue = (new MiddlewareQueue())
            ->add(new RequestLoggingMiddleware())
            ->add(new ErrorHandlerMiddleware($config['Error']))
            ->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use (&$capturedId) {
                $capturedId = $request->getAttribute('requestId');
                Log::write('debug', 'title-secret {password}', [
                    'password' => 'password-secret',
                    'token' => 'token-secret',
                    'session' => 'session-secret',
                    'body' => 'body-secret',
                    'requestId' => 'client-request-id',
                    'safeEvent' => ['event' => 'title-secret', 'source' => 'url-secret'],
                ]);

                return $handler->handle($request);
            });
        $response = (new Runner())->run($queue, $request, $this->throwingHandler($exception));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($baseline->getHeaders(), $response->getHeaders());
        $this->assertSame((string)$baseline->getBody(), (string)$response->getBody());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $capturedId);
        $this->assertNull(RequestLogContext::current());
        $debug = $this->records('debug');
        $errors = $this->records('error');
        $this->assertCount(2, $debug);
        $this->assertCount(1, $errors);
        $this->assertSame('application.log', $debug[0]['event']);
        $this->assertSame('request.completed', $debug[1]['event']);
        $this->assertSame(500, $debug[1]['status']);
        $this->assertGreaterThanOrEqual(0, $debug[1]['durationMs']);
        $this->assertSame('exception.unhandled', $errors[0]['event']);
        $this->assertSame(RuntimeException::class, $errors[0]['exceptionClass']);
        $this->assertSame('tests/TestCase/Middleware/RequestLoggingMiddlewareTest.php', $errors[0]['source']);
        $this->assertGreaterThan(0, $errors[0]['line']);
        foreach ([...$debug, ...$errors] as $record) {
            $this->assertSame($capturedId, $record['requestId']);
            $this->assertSame('POST', $record['method']);
        }
        $this->assertSafeOutput();
    }

    /**
     * Adjacent success/error requests cannot inherit correlation or content.
     */
    public function testAdjacentRequestsAndNonHttpFallbackAreIsolated(): void
    {
        $config = require CONFIG . 'app.php';
        $ids = [];
        $response = new Response(['status' => 201, 'body' => '{"original":"bytes"}']);
        foreach ([false, true, false] as $fails) {
            $queue = (new MiddlewareQueue())
                ->add(new RequestLoggingMiddleware())
                ->add(new ErrorHandlerMiddleware($config['Error']))
                ->add(function (ServerRequestInterface $request) use (&$ids, $fails, $response) {
                    $ids[] = $request->getAttribute('requestId');
                    if ($fails) {
                        throw new RuntimeException('body-secret');
                    }

                    return $response;
                });
            $result = (new Runner())->run($queue, $this->sensitiveRequest());
            if (!$fails) {
                $this->assertSame($response, $result);
            }
            $this->assertNull(RequestLogContext::current());
        }
        $this->assertCount(3, array_unique($ids));
        $completions = $this->records('debug');
        $this->assertSame($ids, array_column($completions, 'requestId'));
        $this->assertSame([201, 500, 201], array_column($completions, 'status'));
        $this->assertSame($ids[1], $this->records('error')[0]['requestId']);

        (new ExceptionTrap($config['Error']))->logException(new RuntimeException('token-secret'));
        $fallback = $this->records('error')[1];
        $this->assertNotContains($fallback['requestId'], $ids);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $fallback['requestId']);
        $this->assertArrayNotHasKey('method', $fallback);
        $this->assertNull(RequestLogContext::current());
        $this->assertSafeOutput();
    }

    /**
     * ErrorTrap and ExceptionTrap both use the safe logger, even with tracing enabled.
     */
    public function testPhpErrorsAnonymousExceptionsAndArbitraryContextAreSafe(): void
    {
        $config = require CONFIG . 'app.php';
        $config['Error']['trace'] = true;
        $errorTrap = new ErrorTrap($config['Error']);
        $exceptionTrap = new ExceptionTrap($config['Error']);
        $this->assertInstanceOf(SafeErrorLogger::class, $errorTrap->logger());
        $this->assertInstanceOf(SafeErrorLogger::class, $exceptionTrap->logger());
        foreach (['debug', 'error'] as $name) {
            $this->assertSame(SafeFileLog::class, Log::getConfig($name)['className']);
            $this->assertArrayNotHasKey('url', Log::getConfig($name));
        }
        $errorTrap->logger()->logError(
            new PhpError(E_USER_WARNING, 'password-secret', __FILE__, __LINE__, [
                ['reference' => 'body-secret', 'file' => 'url-secret', 'line' => 1],
            ]),
            $this->sensitiveRequest(),
            true,
        );
        $errorTrap->logger()->logError(
            new PhpError(E_USER_NOTICE, 'title-secret', '/external/email-secret@example.com.php', 21),
        );
        $exceptionTrap->logException(new class ('session-secret') extends RuntimeException {
        });
        $message = new class implements Stringable {
            public function __toString(): string
            {
                throw new RuntimeException('Messages must never be stringified');
            }
        };
        foreach (Log::levels() as $level) {
            Log::write($level, $message, [
                'scope' => 'password-secret',
                'safeEvent' => $message,
                'context' => ['token' => 'token-secret'],
                'method' => 'email-secret@example.com',
                'source' => 'url-secret',
            ]);
        }
        $errors = $this->records('error');
        $this->assertSame('php.error', $errors[0]['event']);
        $this->assertSame(E_USER_WARNING, $errors[0]['errorLevel']);
        $this->assertSame('tests/TestCase/Middleware/RequestLoggingMiddlewareTest.php', $errors[0]['source']);
        $this->assertSame('anonymous', $errors[1]['exceptionClass']);
        $this->assertSame('unknown', $this->records('debug')[0]['source']);
        $this->assertSafeOutput();
    }

    /**
     * Even an escaping exception restores an enclosing scope and bounds the method.
     */
    public function testEscapingExceptionRestoresEnclosingScope(): void
    {
        $outer = RequestLogContext::create('GET');
        RequestLogContext::set($outer);
        try {
            (new RequestLoggingMiddleware())->process(
                $this->sensitiveRequest()->withMethod('TOKEN-SECRET'),
                $this->throwingHandler(new RuntimeException('body-secret')),
            );
            $this->fail('Expected an escaping exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('body-secret', $exception->getMessage());
        }
        $this->assertSame($outer, RequestLogContext::current());
        $record = $this->records('debug')[0];
        $this->assertSame('OTHER', $record['method']);
        $this->assertSame(500, $record['status']);
        $this->assertNotSame($outer->requestId, $record['requestId']);
        $this->assertSafeOutput();
    }

    /**
     * Real file sink and initialization failures cannot change responses or exceptions.
     */
    public function testReportingFailuresPreserveResponsesAndOriginalExceptions(): void
    {
        mkdir($this->directory, 0777, true);
        mkdir($this->directory . 'debug.log');
        mkdir($this->directory . 'error.log');
        $config = require CONFIG . 'app.php';
        $exception = new RuntimeException('body-secret');
        $request = $this->sensitiveRequest();
        $baseline = (new ErrorHandlerMiddleware([
            'exceptionRenderer' => ApiExceptionRenderer::class,
            'log' => false,
        ]))->process($request, $this->throwingHandler($exception));
        set_error_handler(static function (int $code, string $message): never {
            throw new RuntimeException('File sink is unavailable');
        });
        try {
            foreach (['file', 'initialization'] as $failure) {
                if ($failure === 'initialization') {
                    Log::reset();
                    Log::setConfig('broken', ['className' => 'MissingLogEngine']);
                }
                $success = new Response(['status' => 200, 'body' => '{"unchanged":true}']);
                $queue = (new MiddlewareQueue())
                    ->add(new RequestLoggingMiddleware())
                    ->add(static fn(): Response => $success);
                $this->assertSame($success, (new Runner())->run($queue, $request));
                $this->assertNull(RequestLogContext::current());

                $queue = (new MiddlewareQueue())
                    ->add(new RequestLoggingMiddleware())
                    ->add(new ErrorHandlerMiddleware($config['Error']));
                $result = (new Runner())->run($queue, $request, $this->throwingHandler($exception));
                $this->assertSame($baseline->getStatusCode(), $result->getStatusCode());
                $this->assertSame($baseline->getHeaders(), $result->getHeaders());
                $this->assertSame((string)$baseline->getBody(), (string)$result->getBody());
                $this->assertNull(RequestLogContext::current());
                try {
                    (new RequestLoggingMiddleware())->process($request, $this->throwingHandler($exception));
                    $this->fail('Expected the original exception');
                } catch (RuntimeException $caught) {
                    $this->assertSame($exception, $caught);
                }
                $this->assertNull(RequestLogContext::current());
            }
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return \Cake\Http\ServerRequest
     */
    private function sensitiveRequest(): ServerRequest
    {
        return new ServerRequest([
            'url' => '/api/todos/url-secret?token=token-secret',
            'environment' => ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '192.0.2.123'],
            'headers' => [
                'X-Request-Id' => 'client-request-id',
                'Authorization' => 'Bearer ' . 'token-secret',
                'Cookie' => 'session=session-secret',
                'Referer' => 'https://email-secret@example.com/url-secret',
            ],
            'input' => '{"password":"password-secret","title":"title-secret","body":"body-secret"}',
        ]);
    }

    /**
     * @param \RuntimeException $exception Exception thrown downstream.
     */
    private function throwingHandler(RuntimeException $exception): RequestHandlerInterface
    {
        return new class ($exception) implements RequestHandlerInterface {
            public function __construct(private RuntimeException $exception)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };
    }

    /**
     * @param string $file Debug or error log.
     * @return array<array<string, mixed>>
     */
    private function records(string $file): array
    {
        $lines = file($this->directory . $file . '.log', FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);

        return array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
    }

    /**
     * Assert the actual engine emitted only the exact event-specific allowlist.
     */
    private function assertSafeOutput(): void
    {
        foreach (glob($this->directory . '*.log') ?: [] as $file) {
            $output = file_get_contents($file);
            $sensitiveValues = [
                'password-secret', 'token-secret', 'session-secret', 'title-secret', 'body-secret',
                'url-secret', 'email-secret', '192.0.2.123', 'client-request-id', 'TOKEN-SECRET',
            ];
            foreach ($sensitiveValues as $sensitive) {
                $this->assertStringNotContainsString($sensitive, $output);
            }
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $keys = ['timestamp', 'level', 'requestId', 'event'];
                if (isset($record['method'])) {
                    $keys[] = 'method';
                }
                $keys = array_merge($keys, match ($record['event']) {
                    'request.completed' => ['status', 'durationMs'],
                    'exception.unhandled' => ['exceptionClass', 'source', 'line'],
                    'php.error' => ['errorLevel', 'source', 'line'],
                    default => [],
                });
                $actualKeys = array_keys($record);
                sort($keys);
                sort($actualKeys);
                $this->assertSame($keys, $actualKeys);
            }
        }
    }
}
