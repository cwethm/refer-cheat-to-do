<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.3.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Test\TestCase;

use App\Application;
use App\Middleware\HostHeaderMiddleware;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * ApplicationTest class
 */
class ApplicationTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Test bootstrap in production.
     *
     * @return void
     */
    public function testBootstrap()
    {
        Configure::write('debug', false);
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('Bake'), 'plugins has Bake?');
        $this->assertFalse($plugins->has('DebugKit'), 'plugins has DebugKit?');
        $this->assertTrue($plugins->has('Migrations'), 'plugins has Migrations?');
    }

    /**
     * Test bootstrap add DebugKit plugin in debug mode.
     *
     * @return void
     */
    public function testBootstrapInDebug()
    {
        Configure::write('debug', true);
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $app->bootstrap();
        $plugins = $app->getPlugins();

        $this->assertTrue($plugins->has('DebugKit'), 'plugins has DebugKit?');
    }

    /**
     * The test run must not inherit a deployment base URL.
     *
     * `config/app_local.php` carries the deployed host, and `HostHeaderMiddleware` rejects any
     * request whose host does not match it, so an inherited value fails every integration test
     * with a 400 instead of the expected response.
     *
     * @return void
     */
    public function testTestRunUsesLocalhostBaseUrl()
    {
        $this->assertSame(
            'localhost',
            parse_url((string)Configure::read('App.fullBaseUrl'), PHP_URL_HOST),
        );
    }

    /**
     * testMiddleware
     *
     * @return void
     */
    public function testMiddleware()
    {
        $app = new Application(dirname(__DIR__, 2) . '/config');
        $middleware = new MiddlewareQueue();

        $middleware = $app->middleware($middleware);

        $this->assertInstanceOf(ErrorHandlerMiddleware::class, $middleware->current());
        $middleware->seek(1);
        $this->assertInstanceOf(HostHeaderMiddleware::class, $middleware->current());
        $middleware->seek(2);
        $this->assertInstanceOf(AssetMiddleware::class, $middleware->current());
        $middleware->seek(3);
        $this->assertInstanceOf(RoutingMiddleware::class, $middleware->current());
    }
}
