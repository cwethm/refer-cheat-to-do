<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['prefix' => 'Api', 'controller' => 'Health', 'action' => 'index']);
    });

    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->connect('/health', ['controller' => 'Health', 'action' => 'index', '_method' => 'GET']);

        $builder->connect('/auth/login', ['controller' => 'Auth', 'action' => 'login', '_method' => 'POST']);
        $builder->connect('/auth/logout', ['controller' => 'Auth', 'action' => 'logout', '_method' => 'POST']);
        $builder->connect('/auth/me', ['controller' => 'Auth', 'action' => 'me', '_method' => 'GET']);

        $builder->connect('/workspace', ['controller' => 'Workspace', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/workspace/{userId}', ['controller' => 'Workspace', 'action' => 'view', '_method' => 'GET'])
            ->setPass(['userId'])
            ->setPatterns(['userId' => '\\d+']);
    });
};
