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
        $builder->connect('/auth/login', ['controller' => 'Auth', 'action' => 'login', '_method' => 'POST']);
        $builder->connect('/auth/logout', ['controller' => 'Auth', 'action' => 'logout', '_method' => 'POST']);
        $builder->connect('/auth/me', ['controller' => 'Auth', 'action' => 'me', '_method' => 'GET']);

        $builder->connect('/todos', ['controller' => 'Todos', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/todos', ['controller' => 'Todos', 'action' => 'add', '_method' => 'POST']);
        $builder->connect(
            '/todos/{id}',
            ['controller' => 'Todos', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}',
            ['controller' => 'Todos', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect('/tags', ['controller' => 'Tags', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/tags', ['controller' => 'Tags', 'action' => 'add', '_method' => 'POST']);
        $builder->connect(
            '/tags/{id}',
            ['controller' => 'Tags', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/tags/{id}',
            ['controller' => 'Tags', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/tags/{tagId}',
            ['controller' => 'Todos', 'action' => 'attachTag', '_method' => 'POST'],
            ['pass' => ['id', 'tagId'], 'id' => '\d+', 'tagId' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/tags/{tagId}',
            ['controller' => 'Todos', 'action' => 'detachTag', '_method' => 'DELETE'],
            ['pass' => ['id', 'tagId'], 'id' => '\d+', 'tagId' => '\d+'],
        );
        $builder->connect('/health', ['controller' => 'Health', 'action' => 'index', '_method' => 'GET']);
    });
};
