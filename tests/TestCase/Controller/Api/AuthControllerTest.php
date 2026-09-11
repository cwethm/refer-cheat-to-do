<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    /**
     * @return array<string, mixed>
     */
    protected function identitySession(): array
    {
        return [
            'Auth.user_id' => 1,
            'Auth.identity' => [
                'id' => 1,
                'email' => 'owner@example.com',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];
    }

    public function testLoginSucceedsWithCorrectCredentials(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('"id": 1');
        $this->assertResponseContains('"email": "owner@example.com"');
        $this->assertResponseNotContains('"password"');
        $this->assertSession(1, 'Auth.user_id');
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong',
        ]);

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testLoginSucceedsWithWhitespaceAroundEmail(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/auth/login', [
            'email' => '  OWNER@example.com ',
            'password' => 'Password123!',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('"id": 1');
        $this->assertSession(1, 'Auth.user_id');
    }

    public function testLoginFailsWithMissingCredentials(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/auth/login', []);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
        $this->assertResponseContains('"email"');
        $this->assertResponseContains('"password"');
    }

    public function testMeReturnsCurrentUserWhenAuthenticated(): void
    {
        $this->session($this->identitySession());
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/auth/me');

        $this->assertResponseOk();
        $this->assertResponseContains('"id": 1');
        $this->assertResponseContains('"owner@example.com"');
    }

    public function testMeDeniesAnonymousUser(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testLogoutInvalidatesSession(): void
    {
        $this->session($this->identitySession());
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/auth/logout', []);
        $this->assertResponseOk();
        $this->assertSession(null, 'Auth.user_id');

        $this->get('/api/auth/me');
        $this->assertResponseCode(401);
    }

    public function testLogoutInvalidatesSessionWithIdentityPayload(): void
    {
        $session = $this->identitySession();
        unset($session['Auth.user_id']);
        $this->session($session);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/auth/logout', []);
        $this->assertResponseOk();
        $this->assertSession(null, 'Auth.user_id');
        $this->assertSession(null, 'Auth.identity');
    }
}
