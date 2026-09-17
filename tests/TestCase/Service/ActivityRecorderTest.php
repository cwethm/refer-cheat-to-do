<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ActivityRecorder;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;
use stdClass;

class ActivityRecorderTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
    ];

    private function recorder(): ActivityRecorder
    {
        return new ActivityRecorder(TableRegistry::getTableLocator());
    }

    public function testRecordPersistsRecord(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10, ['status' => 'inbox']);

        $this->assertGreaterThan(0, (int)$record->id);
        $this->assertSame(1, (int)$record->actor_user_id);
        $this->assertSame('todo.created', $record->action);
        $this->assertSame('todo', $record->subject_type);
        $this->assertSame(10, (int)$record->subject_id);
        $this->assertSame(['status' => 'inbox'], $record->metadataArray());
        $this->assertNotNull($record->created);
    }

    public function testRecordStoresOptionalContext(): void
    {
        $record = $this->recorder()->record(1, 'todo.updated', 'todo', 10, [], 'project', 200);

        $this->assertSame('project', $record->context_type);
        $this->assertSame(200, (int)$record->context_id);
    }

    public function testRecordWithoutMetadataStoresNull(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10);

        $this->assertNull($record->metadata);
        $this->assertSame([], $record->metadataArray());
    }

    public function testActionIsNormalized(): void
    {
        $record = $this->recorder()->record(1, '  Todo.Created   Again ', 'todo', 10);

        $this->assertSame('todo.created again', $record->action);
    }

    public function testEmptyActionIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, '   ', 'todo', 10);
    }

    public function testOverlongActionIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, str_repeat('a', 65), 'todo', 10);
    }

    public function testUnknownSubjectTypeIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, 'thing.created', 'unicorn', 10);
    }

    public function testUnknownContextTypeIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, 'todo.created', 'todo', 10, [], 'unicorn', 1);
    }

    public function testPartialContextIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, 'todo.created', 'todo', 10, [], 'project', null);
    }

    public function testPartialContextIdWithoutTypeIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->record(1, 'todo.created', 'todo', 10, [], null, 200);
    }

    public function testSensitiveMetadataKeysAreRedacted(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10, [
            'password' => 'hunter2',
            'TOKEN' => 'abc',
            'secret' => 's',
            'api_key' => 'k',
            'authorization' => '******',
            'credential' => 'c',
            'status' => 'inbox',
        ]);

        $this->assertSame(['status' => 'inbox'], $record->metadataArray());
        $this->assertStringNotContainsString('hunter2', (string)$record->metadata);
    }

    public function testNestedSensitiveMetadataIsRedacted(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10, [
            'payload' => ['password' => 'hunter2', 'title' => 'Ship it'],
        ]);

        $this->assertSame(['payload' => ['title' => 'Ship it']], $record->metadataArray());
    }

    public function testLongMetadataValuesAreTruncated(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10, [
            'title' => str_repeat('x', 400),
        ]);

        $this->assertSame(ActivityRecorder::MAX_VALUE_LENGTH, strlen((string)$record->metadataArray()['title']));
    }

    public function testUnsupportedMetadataValuesAreDropped(): void
    {
        $record = $this->recorder()->record(1, 'todo.created', 'todo', 10, [
            'resource' => new stdClass(),
            'ok' => true,
            'count' => 3,
            'ratio' => 1.5,
            'nothing' => null,
        ]);

        $this->assertSame(
            ['ok' => true, 'count' => 3, 'ratio' => 1.5, 'nothing' => null],
            $record->metadataArray(),
        );
    }

    public function testForActorReturnsOnlyOwnRecordsNewestFirst(): void
    {
        $recorder = $this->recorder();
        $first = $recorder->record(1, 'todo.created', 'todo', 10);
        $second = $recorder->record(1, 'todo.updated', 'todo', 10);
        $recorder->record(2, 'todo.created', 'todo', 12);

        $ids = [];
        foreach ($recorder->forActor(1) as $record) {
            $ids[] = (int)$record->id;
        }

        $this->assertSame([(int)$second->id, (int)$first->id], $ids);
    }

    public function testForActorFiltersBySubject(): void
    {
        $recorder = $this->recorder();
        $recorder->record(1, 'todo.created', 'todo', 10);
        $recorder->record(1, 'project.created', 'project', 200);

        $results = $recorder->forActor(1, ['subject_type' => 'project'])->toArray();

        $this->assertCount(1, $results);
        $this->assertSame('project', $results[0]->subject_type);
    }

    public function testForActorFiltersBySubjectId(): void
    {
        $recorder = $this->recorder();
        $recorder->record(1, 'todo.created', 'todo', 10);
        $recorder->record(1, 'todo.created', 'todo', 11);

        $results = $recorder->forActor(1, ['subject_id' => '11'])->toArray();

        $this->assertCount(1, $results);
        $this->assertSame(11, (int)$results[0]->subject_id);
    }

    public function testForActorFiltersByAction(): void
    {
        $recorder = $this->recorder();
        $recorder->record(1, 'todo.created', 'todo', 10);
        $recorder->record(1, 'todo.updated', 'todo', 10);

        $results = $recorder->forActor(1, ['action' => ' TODO.UPDATED '])->toArray();

        $this->assertCount(1, $results);
        $this->assertSame('todo.updated', $results[0]->action);
    }

    public function testForActorIgnoresEmptyFilters(): void
    {
        $recorder = $this->recorder();
        $recorder->record(1, 'todo.created', 'todo', 10);

        $results = $recorder->forActor(1, ['subject_type' => '', 'subject_id' => '', 'action' => '   '])->toArray();

        $this->assertCount(1, $results);
    }

    public function testForActorRejectsUnknownSubjectType(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->forActor(1, ['subject_type' => 'unicorn']);
    }

    public function testForActorRejectsInvalidSubjectId(): void
    {
        $this->expectException(DomainException::class);

        $this->recorder()->forActor(1, ['subject_id' => 'abc']);
    }

    public function testRedactIsIdempotent(): void
    {
        $recorder = $this->recorder();
        $once = $recorder->redact(['password' => 'x', 'a' => 'b']);

        $this->assertSame($once, $recorder->redact($once));
    }
}
