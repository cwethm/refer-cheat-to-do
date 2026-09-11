<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class WorkspaceControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    public function testOwnerCanAccessOwnWorkspace(): void
    {
        $this->session([
            'Auth.user_id' => 1,
            'Auth.identity' => [
                'id' => 1,
                'email' => 'owner@example.com',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/workspace/1');

        $this->assertResponseOk();
        $this->assertResponseContains('"user_id": 1');
    }

    public function testDifferentAuthenticatedUserIsDenied(): void
    {
        $this->session([
            'Auth.user_id' => 2,
            'Auth.identity' => [
                'id' => 2,
                'email' => 'other@example.com',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/workspace/1');

        $this->assertResponseCode(403);
        $this->assertResponseContains('"code": "FORBIDDEN"');
    }

    public function testAnonymousUserIsDenied(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/workspace/1');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }
}
