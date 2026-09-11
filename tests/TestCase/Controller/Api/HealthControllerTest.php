<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HealthControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testRootRouteReturnsHealthPayload(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('"status": "ok"');
    }

    public function testHealthEndpointReturnsJsonPayload(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/health');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('"status": "ok"');
        $this->assertResponseContains('"name": "refer-cheat-to-do"');
    }

    public function testApiNotFoundUsesJsonErrorConvention(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/missing-endpoint');

        $this->assertResponseCode(404);
        $this->assertContentType('application/json');
        $this->assertResponseContains('"code": "NOT_FOUND"');
        $this->assertResponseContains('"message"');
        $this->assertResponseContains('"details"');
    }
}
