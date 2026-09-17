<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AuthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
    ];

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'owner-password',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('"email": "owner@example.com"');
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertResponseCode(401);
        $this->assertContentType('application/json');
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testLoginFailsWithMissingCredentials(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/auth/login', [
            'email' => 'owner@example.com',
        ]);

        $this->assertResponseCode(400);
        $this->assertContentType('application/json');
        $this->assertResponseContains('"code": "BAD_REQUEST"');
    }

    public function testMeReturnsCurrentAuthenticatedUser(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->get('/api/auth/me');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('"email": "owner@example.com"');
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/auth/me');

        $this->assertResponseCode(401);
        $this->assertContentType('application/json');
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testLogoutInvalidatesSession(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/api/auth/logout', []);
        $this->assertResponseOk();
        $this->assertSession(null, 'Auth.user_id');
    }
}
