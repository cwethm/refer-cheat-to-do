<?php
declare(strict_types=1);

namespace App;

use App\Middleware\HostHeaderMiddleware;
use App\Middleware\RequestLoggingMiddleware;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventManagerInterface;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup and middleware configuration.
 */
class Application extends BaseApplication
{
    /**
     * Bootstrap application services and framework configuration.
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        FactoryLocator::add(
            'Table',
            (new TableLocator())->allowFallbackClass(false),
        );
    }

    /**
     * Configure and return the application middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $csrfMiddleware = new CsrfProtectionMiddleware([
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $csrfMiddleware->skipCheckCallback(
            static fn(ServerRequestInterface $request): bool => preg_match(
                '#^/api/(?:reference-cards|cheat-sheets|records|invitations|auth/password|auth/password-reset)(?:/|$)#',
                $request->getUri()->getPath(),
            ) !== 1,
        );

        $middlewareQueue
            ->add(new RequestLoggingMiddleware())
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
            ->add(new HostHeaderMiddleware())
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware())
            ->add($csrfMiddleware);

        return $middlewareQueue;
    }

    /**
     * Register container services.
     */
    public function services(ContainerInterface $container): void
    {
    }

    /**
     * Register and return the application event manager.
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        return $eventManager;
    }
}
