<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Library;
use App\Model\Table\LibrariesTable;
use App\Service\LibraryMembershipService;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;

class LibraryMembershipServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
    ];

    protected LibrariesTable $Libraries;

    protected LibraryMembershipService $membership;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\LibrariesTable $libraries */
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $this->Libraries = $libraries;
        $this->membership = new LibraryMembershipService(TableRegistry::getTableLocator());
    }

    protected function tearDown(): void
    {
        unset($this->Libraries, $this->membership);

        parent::tearDown();
    }

    private function library(int $id = 600): Library
    {
        /** @var \App\Model\Entity\Library $library */
        $library = $this->Libraries->get($id);

        return $library;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    public static function memberTypeProvider(): array
    {
        return [
            [LibraryMembershipService::MEMBER_PROJECT, 200],
            [LibraryMembershipService::MEMBER_NOTEBOOK, 400],
        ];
    }

    #[DataProvider('memberTypeProvider')]
    public function testAddAndListMember(string $memberType, int $memberId): void
    {
        $this->membership->addMember($this->library(), $memberType, $memberId);

        $this->assertSame([$memberId], $this->membership->memberIds($this->library(), $memberType));
    }

    #[DataProvider('memberTypeProvider')]
    public function testRemoveMemberKeepsTheMemberItself(string $memberType, int $memberId): void
    {
        $this->membership->addMember($this->library(), $memberType, $memberId);
        $this->membership->removeMember($this->library(), $memberType, $memberId);

        $this->assertSame([], $this->membership->memberIds($this->library(), $memberType));
        $table = $memberType === LibraryMembershipService::MEMBER_PROJECT ? 'Projects' : 'Notebooks';
        $this->assertTrue(TableRegistry::getTableLocator()->get($table)->exists(['id' => $memberId]));
    }

    #[DataProvider('memberTypeProvider')]
    public function testDuplicateMembershipIsRejected(string $memberType, int $memberId): void
    {
        $this->membership->addMember($this->library(), $memberType, $memberId);

        $this->expectException(DomainException::class);
        $this->membership->addMember($this->library(), $memberType, $memberId);
    }

    #[DataProvider('memberTypeProvider')]
    public function testRemovingAbsentMembershipIsRejected(string $memberType, int $memberId): void
    {
        $this->expectException(DomainException::class);
        $this->membership->removeMember($this->library(), $memberType, $memberId);
    }

    public function testCrossOwnerProjectMembershipIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_PROJECT, 201);
    }

    public function testCrossOwnerNotebookMembershipIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_NOTEBOOK, 401);
    }

    public function testNonexistentMemberIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_PROJECT, 999999);
    }

    public function testUnknownMemberTypeIsRejected(): void
    {
        $this->assertFalse($this->membership->isSupportedType('todo'));

        $this->expectException(DomainException::class);
        $this->membership->addMember($this->library(), 'todo', 200);
    }

    public function testSameProjectCanBelongToMultipleLibraries(): void
    {
        $this->membership->addMember($this->library(600), LibraryMembershipService::MEMBER_PROJECT, 200);
        $this->membership->addMember($this->library(601), LibraryMembershipService::MEMBER_PROJECT, 200);

        $this->assertSame(
            [600, 601],
            $this->membership->libraryIdsFor(LibraryMembershipService::MEMBER_PROJECT, 200),
        );
    }

    public function testSameNotebookCanBelongToMultipleLibraries(): void
    {
        $this->membership->addMember($this->library(600), LibraryMembershipService::MEMBER_NOTEBOOK, 400);
        $this->membership->addMember($this->library(601), LibraryMembershipService::MEMBER_NOTEBOOK, 400);

        $this->assertSame(
            [600, 601],
            $this->membership->libraryIdsFor(LibraryMembershipService::MEMBER_NOTEBOOK, 400),
        );
    }

    public function testHasMembersCoversBothMemberKinds(): void
    {
        $this->assertFalse($this->membership->hasMembers($this->library()));

        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_NOTEBOOK, 400);
        $this->assertTrue($this->membership->hasMembers($this->library()));
    }

    public function testDatabaseRejectsDuplicateProjectMembership(): void
    {
        $connection = $this->Libraries->getConnection();
        $connection->insert('libraries_projects', ['library_id' => 600, 'project_id' => 200]);

        $this->expectException(QueryException::class);
        $connection->insert('libraries_projects', ['library_id' => 600, 'project_id' => 200]);
    }

    public function testDatabaseRejectsDuplicateNotebookMembership(): void
    {
        $connection = $this->Libraries->getConnection();
        $connection->insert('libraries_notebooks', ['library_id' => 600, 'notebook_id' => 400]);

        $this->expectException(QueryException::class);
        $connection->insert('libraries_notebooks', ['library_id' => 600, 'notebook_id' => 400]);
    }

    public function testDatabaseRejectsMembershipForUnknownProject(): void
    {
        $this->expectException(QueryException::class);
        $this->Libraries->getConnection()
            ->insert('libraries_projects', ['library_id' => 600, 'project_id' => 999999]);
    }

    public function testDeletingLibraryDoesNotDeleteMembers(): void
    {
        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_PROJECT, 200);
        $this->membership->addMember($this->library(), LibraryMembershipService::MEMBER_NOTEBOOK, 400);

        $this->Libraries->deleteOrFail($this->library());

        $locator = TableRegistry::getTableLocator();
        $this->assertTrue($locator->get('Projects')->exists(['id' => 200]));
        $this->assertTrue($locator->get('Notebooks')->exists(['id' => 400]));
        $this->assertFalse($locator->get('LibrariesProjects')->exists(['library_id' => 600]));
        $this->assertFalse($locator->get('LibrariesNotebooks')->exists(['library_id' => 600]));
    }
}
