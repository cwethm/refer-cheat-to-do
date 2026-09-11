<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TagsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class TagsTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Tags',
    ];

    protected TagsTable $Tags;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TagsTable $tags */
        $tags = TableRegistry::getTableLocator()->get('Tags');
        $this->Tags = $tags;
    }

    protected function tearDown(): void
    {
        unset($this->Tags);

        parent::tearDown();
    }

    public function testValidationRejectsBlankName(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '',
        ]);

        $this->assertTrue($tag->hasErrors());
        $this->assertArrayHasKey('name', $tag->getErrors());
    }

    public function testNameNormalizationCollapsesWhitespace(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '  Research   Queue  ',
        ]);

        $this->assertFalse($tag->hasErrors());
        $this->assertSame('Research Queue', $tag->name);
    }

    public function testValidationRejectsDuplicateNormalizedNamePerUser(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '  important  ',
        ]);

        $saved = $this->Tags->save($tag);
        $this->assertFalse($saved);
        $this->assertArrayHasKey('name', $tag->getErrors());
    }

    public function testAllowsSameTagNameForDifferentUser(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 2,
            'name' => 'Important',
        ]);

        $saved = $this->Tags->save($tag);
        $this->assertNotFalse($saved);
    }
}
