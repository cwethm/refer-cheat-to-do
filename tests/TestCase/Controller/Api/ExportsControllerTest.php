<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Service\DataPortabilityService;
use App\Test\TestCase\SignedInSessionTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ExportsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use SignedInSessionTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.RelatedTodos',
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
        'app.ActivityRecords',
        'app.CapabilityGrants',
        'app.CrossContextRequests',
    ];

    public function testAnonymousExportIsDenied(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/export');
        $this->assertResponseCode(401);
        $this->assertFalse($this->_response?->hasHeader('Content-Disposition'));
    }

    public function testDownloadIsPrivateAndCannotSelectAnotherUser(): void
    {
        $this->signIn(1);
        $this->get('/api/export?user_id=2');

        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'application/x-ndjson');
        $this->assertHeader('Cache-Control', 'no-store');
        $this->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertHeaderContains('Content-Disposition', 'cheat-to-do-export-v1.jsonl');
        $body = (string)$this->_response?->getBody();
        $header = json_decode(explode("\n", $body)[0], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(DataPortabilityService::SCHEMA, $header['schema']);
        $this->assertStringContainsString('Owner inbox todo', $body);
        $this->assertStringNotContainsString('Other user todo', $body);
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('"token"', $body);
    }
}
