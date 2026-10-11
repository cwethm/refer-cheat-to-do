<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PagesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testAppPageServesTheAccessibleSameOriginClient(): void
    {
        $this->get('/app');

        $this->assertResponseOk();
        $this->assertContentType('text/html');
        $this->assertMatchesRegularExpression(
            '/<meta name="csrf-token" content="[A-Za-z0-9+\/=]+">/',
            $this->responseBody(),
        );
        $this->assertResponseContains('<html lang="en">');
        $this->assertResponseContains('href="#main-content"');
        $this->assertResponseContains('aria-label="Workspace views"');
        $this->assertResponseContains('id="login-form"');
        $this->assertResponseContains('id="capture-form"');
        $this->assertResponseContains('id="filter-form"');
        $this->assertResponseContains('id="project-form"');
        $this->assertResponseContains('id="project-section-form"');
        $this->assertResponseContains('id="project-list"');
        $this->assertResponseContains('id="notebook-form"');
        $this->assertResponseContains('id="notebook-section-form"');
        $this->assertResponseContains('id="notebook-list"');
        $this->assertResponseContains('id="library-list"');
        $this->assertResponseContains('id="review-list"');
        $this->assertResponseContains('id="todo-detail"');
        $this->assertResponseContains('id="todo-parent-form"');
        $this->assertResponseContains('id="todo-relate-form"');
        $this->assertResponseContains('id="todo-objective-form"');
        $this->assertResponseContains('id="todo-result-form"');
        $this->assertResponseContains('id="todo-lifecycle-actions"');
        $this->assertResponseContains('id="activity-list"');
        $this->assertResponseContains('data-view="activity"');
        $this->assertResponseContains('src="/js/app.mjs?');
        $this->assertResponseContains('href="/css/app.css?');
    }

    public function testReferenceMutationsRequireCsrfToken(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->post('/api/reference-cards', '{"title":"Missing token"}');

        $this->assertResponseCode(403);
    }

    public function testAppPageDoesNotLoadThirdPartyScriptsOrFonts(): void
    {
        $this->get('/app');

        $this->assertResponseOk();
        $this->assertResponseNotContains('https://');
        $this->assertResponseNotContains('http://');
    }
}
