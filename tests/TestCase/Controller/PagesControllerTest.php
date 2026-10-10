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
        $this->assertResponseContains('<html lang="en">');
        $this->assertResponseContains('href="#main-content"');
        $this->assertResponseContains('aria-label="Workspace views"');
        $this->assertResponseContains('id="login-form"');
        $this->assertResponseContains('id="capture-form"');
        $this->assertResponseContains('id="filter-form"');
        $this->assertResponseContains('id="project-form"');
        $this->assertResponseContains('id="section-form"');
        $this->assertResponseContains('id="project-list"');
        $this->assertResponseContains('id="review-list"');
        $this->assertResponseContains('src="/js/app.mjs?');
        $this->assertResponseContains('href="/css/app.css?');
    }

    public function testAppPageDoesNotLoadThirdPartyScriptsOrFonts(): void
    {
        $this->get('/app');

        $this->assertResponseOk();
        $this->assertResponseNotContains('https://');
        $this->assertResponseNotContains('http://');
    }
}
