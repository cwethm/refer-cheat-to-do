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
        $builder->connect(
            '/todos/review-queue',
            ['controller' => 'Todos', 'action' => 'reviewQueue', '_method' => 'GET'],
        );
        $builder->connect(
            '/todos/{id}/reviewed',
            ['controller' => 'Todos', 'action' => 'markReviewed', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/snooze',
            ['controller' => 'Todos', 'action' => 'snooze', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        foreach (['activate', 'complete', 'archive', 'trash', 'restore'] as $lifecycleAction) {
            $builder->connect(
                '/todos/{id}/' . $lifecycleAction,
                ['controller' => 'Todos', 'action' => $lifecycleAction, '_method' => 'POST'],
                ['pass' => ['id'], 'id' => '\d+'],
            );
        }
        $builder->connect(
            '/todos/{id}/permanent',
            ['controller' => 'Todos', 'action' => 'permanentDelete', '_method' => 'DELETE'],
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
        $builder->connect('/projects', ['controller' => 'Projects', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/projects', ['controller' => 'Projects', 'action' => 'add', '_method' => 'POST']);
        $builder->connect(
            '/projects/{id}',
            ['controller' => 'Projects', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}',
            ['controller' => 'Projects', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}',
            ['controller' => 'Projects', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}/sections',
            ['controller' => 'Projects', 'action' => 'sections', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}/sections',
            ['controller' => 'Projects', 'action' => 'addSection', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}/sections/{sectionId}',
            ['controller' => 'Projects', 'action' => 'editSection', '_method' => 'PATCH'],
            ['pass' => ['id', 'sectionId'], 'id' => '\d+', 'sectionId' => '\d+'],
        );
        $builder->connect(
            '/projects/{id}/sections/{sectionId}',
            ['controller' => 'Projects', 'action' => 'deleteSection', '_method' => 'DELETE'],
            ['pass' => ['id', 'sectionId'], 'id' => '\d+', 'sectionId' => '\d+'],
        );
        $builder->connect('/notebooks', ['controller' => 'Notebooks', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/notebooks', ['controller' => 'Notebooks', 'action' => 'add', '_method' => 'POST']);
        $builder->connect(
            '/notebooks/{id}',
            ['controller' => 'Notebooks', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}',
            ['controller' => 'Notebooks', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}',
            ['controller' => 'Notebooks', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}/sections',
            ['controller' => 'Notebooks', 'action' => 'sections', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}/sections',
            ['controller' => 'Notebooks', 'action' => 'addSection', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}/sections/{sectionId}',
            ['controller' => 'Notebooks', 'action' => 'editSection', '_method' => 'PATCH'],
            ['pass' => ['id', 'sectionId'], 'id' => '\d+', 'sectionId' => '\d+'],
        );
        $builder->connect(
            '/notebooks/{id}/sections/{sectionId}',
            ['controller' => 'Notebooks', 'action' => 'deleteSection', '_method' => 'DELETE'],
            ['pass' => ['id', 'sectionId'], 'id' => '\d+', 'sectionId' => '\d+'],
        );
        $builder->connect('/health', ['controller' => 'Health', 'action' => 'index', '_method' => 'GET']);
    });
};
