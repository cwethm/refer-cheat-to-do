<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/app', ['controller' => 'Pages', 'action' => 'display', 'app'], ['_method' => 'GET']);
        $builder->connect('/', ['prefix' => 'Api', 'controller' => 'Health', 'action' => 'index']);
    });

    $routes->prefix('Api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->connect('/auth/login', ['controller' => 'Auth', 'action' => 'login', '_method' => 'POST']);
        $builder->connect('/auth/logout', ['controller' => 'Auth', 'action' => 'logout', '_method' => 'POST']);
        $builder->connect('/auth/me', ['controller' => 'Auth', 'action' => 'me', '_method' => 'GET']);
        $builder->connect('/export', ['controller' => 'Exports', 'action' => 'download', '_method' => 'GET']);

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
            '/todos/{id}/hierarchy',
            ['controller' => 'Todos', 'action' => 'hierarchy', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/parent',
            ['controller' => 'Todos', 'action' => 'setParent', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/objective',
            ['controller' => 'Todos', 'action' => 'setObjective', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/result',
            ['controller' => 'Todos', 'action' => 'reportResult', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/related/{relatedId}',
            ['controller' => 'Todos', 'action' => 'relate', '_method' => 'POST'],
            ['pass' => ['id', 'relatedId'], 'id' => '\d+', 'relatedId' => '\d+'],
        );
        $builder->connect(
            '/todos/{id}/related/{relatedId}',
            ['controller' => 'Todos', 'action' => 'unrelate', '_method' => 'DELETE'],
            ['pass' => ['id', 'relatedId'], 'id' => '\d+', 'relatedId' => '\d+'],
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
        $builder->connect('/libraries', ['controller' => 'Libraries', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/libraries', ['controller' => 'Libraries', 'action' => 'add', '_method' => 'POST']);
        $builder->connect(
            '/libraries/{id}',
            ['controller' => 'Libraries', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/libraries/{id}',
            ['controller' => 'Libraries', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/libraries/{id}',
            ['controller' => 'Libraries', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/libraries/{id}/members',
            ['controller' => 'Libraries', 'action' => 'members', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/libraries/{id}/members/{memberType}/{memberId}',
            ['controller' => 'Libraries', 'action' => 'addMember', '_method' => 'POST'],
            [
                'pass' => ['id', 'memberType', 'memberId'],
                'id' => '\d+',
                'memberType' => '[a-z]+',
                'memberId' => '\d+',
            ],
        );
        $builder->connect(
            '/libraries/{id}/members/{memberType}/{memberId}',
            ['controller' => 'Libraries', 'action' => 'removeMember', '_method' => 'DELETE'],
            [
                'pass' => ['id', 'memberType', 'memberId'],
                'id' => '\d+',
                'memberType' => '[a-z]+',
                'memberId' => '\d+',
            ],
        );
        $builder->connect(
            '/capabilities',
            ['controller' => 'Capabilities', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect(
            '/capabilities',
            ['controller' => 'Capabilities', 'action' => 'add', '_method' => 'POST'],
        );
        $builder->connect(
            '/capabilities/{id}',
            ['controller' => 'Capabilities', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections',
            ['controller' => 'ResearchCollections', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect(
            '/research-collections',
            ['controller' => 'ResearchCollections', 'action' => 'add', '_method' => 'POST'],
        );
        $builder->connect(
            '/research-collections/{id}',
            ['controller' => 'ResearchCollections', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}',
            ['controller' => 'ResearchCollections', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}',
            ['controller' => 'ResearchCollections', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}/items',
            ['controller' => 'ResearchCollections', 'action' => 'items', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}/items',
            ['controller' => 'ResearchCollections', 'action' => 'addItem', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}/items/{itemId}',
            ['controller' => 'ResearchCollections', 'action' => 'viewItem', '_method' => 'GET'],
            ['pass' => ['id', 'itemId'], 'id' => '\d+', 'itemId' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}/items/{itemId}',
            ['controller' => 'ResearchCollections', 'action' => 'editItem', '_method' => 'PATCH'],
            ['pass' => ['id', 'itemId'], 'id' => '\d+', 'itemId' => '\d+'],
        );
        $builder->connect(
            '/research-collections/{id}/items/{itemId}',
            ['controller' => 'ResearchCollections', 'action' => 'deleteItem', '_method' => 'DELETE'],
            ['pass' => ['id', 'itemId'], 'id' => '\d+', 'itemId' => '\d+'],
        );
        $builder->connect(
            '/reference-cards',
            ['controller' => 'ReferenceCards', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect(
            '/reference-cards',
            ['controller' => 'ReferenceCards', 'action' => 'add', '_method' => 'POST'],
        );
        $builder->connect(
            '/reference-cards/{id}',
            ['controller' => 'ReferenceCards', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/reference-cards/{id}',
            ['controller' => 'ReferenceCards', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/reference-cards/{id}',
            ['controller' => 'ReferenceCards', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/reference-cards/{id}/tags/{tagId}',
            ['controller' => 'ReferenceCards', 'action' => 'attachTag', '_method' => 'POST'],
            ['pass' => ['id', 'tagId'], 'id' => '\d+', 'tagId' => '\d+'],
        );
        $builder->connect(
            '/reference-cards/{id}/tags/{tagId}',
            ['controller' => 'ReferenceCards', 'action' => 'detachTag', '_method' => 'DELETE'],
            ['pass' => ['id', 'tagId'], 'id' => '\d+', 'tagId' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets',
            ['controller' => 'CheatSheets', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect(
            '/cheat-sheets',
            ['controller' => 'CheatSheets', 'action' => 'add', '_method' => 'POST'],
        );
        $builder->connect(
            '/cheat-sheets/{id}',
            ['controller' => 'CheatSheets', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}',
            ['controller' => 'CheatSheets', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}',
            ['controller' => 'CheatSheets', 'action' => 'delete', '_method' => 'DELETE'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}/cards',
            ['controller' => 'CheatSheets', 'action' => 'cards', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}/cards',
            ['controller' => 'CheatSheets', 'action' => 'addCard', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}/cards/{cardId}',
            ['controller' => 'CheatSheets', 'action' => 'moveCard', '_method' => 'PATCH'],
            ['pass' => ['id', 'cardId'], 'id' => '\d+', 'cardId' => '\d+'],
        );
        $builder->connect(
            '/cheat-sheets/{id}/cards/{cardId}',
            ['controller' => 'CheatSheets', 'action' => 'removeCard', '_method' => 'DELETE'],
            ['pass' => ['id', 'cardId'], 'id' => '\d+', 'cardId' => '\d+'],
        );
        $builder->connect(
            '/cross-context-requests',
            ['controller' => 'CrossContextRequests', 'action' => 'add', '_method' => 'POST'],
        );
        $builder->connect(
            '/cross-context-requests/inbox',
            ['controller' => 'CrossContextRequests', 'action' => 'inbox', '_method' => 'GET'],
        );
        $builder->connect(
            '/cross-context-requests/outbox',
            ['controller' => 'CrossContextRequests', 'action' => 'outbox', '_method' => 'GET'],
        );
        $builder->connect(
            '/cross-context-requests/{id}',
            ['controller' => 'CrossContextRequests', 'action' => 'view', '_method' => 'GET'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cross-context-requests/{id}',
            ['controller' => 'CrossContextRequests', 'action' => 'edit', '_method' => 'PATCH'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cross-context-requests/{id}/accept',
            ['controller' => 'CrossContextRequests', 'action' => 'accept', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cross-context-requests/{id}/reject',
            ['controller' => 'CrossContextRequests', 'action' => 'reject', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/cross-context-requests/{id}/callback',
            ['controller' => 'CrossContextRequests', 'action' => 'callback', '_method' => 'POST'],
            ['pass' => ['id'], 'id' => '\d+'],
        );
        $builder->connect(
            '/dashboard',
            ['controller' => 'Dashboard', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect(
            '/activity',
            ['controller' => 'Activity', 'action' => 'index', '_method' => 'GET'],
        );
        $builder->connect('/health', ['controller' => 'Health', 'action' => 'index', '_method' => 'GET']);
    });
};
