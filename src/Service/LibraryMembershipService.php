<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Library;
use Cake\ORM\Locator\LocatorInterface;
use DomainException;
use Throwable;

/**
 * Single authority for Library membership mechanics.
 *
 * Projects and Notebooks keep separate domain models; only the identical membership mechanics are
 * shared here. Membership is context only and never grants authority over a member.
 */
class LibraryMembershipService
{
    public const MEMBER_PROJECT = 'project';
    public const MEMBER_NOTEBOOK = 'notebook';

    /**
     * Join configuration per member kind.
     *
     * @var array<string, array{join: string, key: string, table: string}>
     */
    private const MEMBER_TYPES = [
        self::MEMBER_PROJECT => [
            'join' => 'LibrariesProjects',
            'key' => 'project_id',
            'table' => 'Projects',
        ],
        self::MEMBER_NOTEBOOK => [
            'join' => 'LibrariesNotebooks',
            'key' => 'notebook_id',
            'table' => 'Notebooks',
        ],
    ];

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve join tables.
     */
    public function __construct(private LocatorInterface $tables)
    {
    }

    /**
     * Whether the given member kind is supported.
     */
    public function isSupportedType(string $memberType): bool
    {
        return array_key_exists($memberType, self::MEMBER_TYPES);
    }

    /**
     * Add a member owned by the Library owner.
     *
     * @throws \DomainException When the member is already present or not owned by the same user.
     */
    public function addMember(Library $library, string $memberType, int $memberId): void
    {
        $config = $this->configFor($memberType);
        $this->assertOwnedMember($config['table'], $memberId, (int)$library->user_id);

        $join = $this->tables->get($config['join']);
        $entity = $join->newEntity([
            'library_id' => (int)$library->id,
            $config['key'] => $memberId,
        ], ['accessibleFields' => ['library_id' => true, $config['key'] => true]]);

        try {
            $saved = $join->save($entity, ['checkExisting' => false, 'checkRules' => false]);
        } catch (Throwable $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new DomainException('This member is already in the library.');
            }

            throw $exception;
        }
        if ($saved === false) {
            throw new DomainException('This member is already in the library.');
        }
    }

    /**
     * Remove a member from a Library. The member itself is never deleted.
     *
     * @throws \DomainException When the membership does not exist.
     */
    public function removeMember(Library $library, string $memberType, int $memberId): void
    {
        $config = $this->configFor($memberType);
        $deleted = $this->tables->get($config['join'])->deleteAll([
            'library_id' => (int)$library->id,
            $config['key'] => $memberId,
        ]);
        if ($deleted < 1) {
            throw new DomainException('This member is not in the library.');
        }
    }

    /**
     * Ids of the members of a Library for one member kind.
     *
     * @return list<int>
     */
    public function memberIds(Library $library, string $memberType): array
    {
        $config = $this->configFor($memberType);
        $ids = [];
        $rows = $this->tables->get($config['join'])->find()
            ->where(['library_id' => (int)$library->id])
            ->orderBy([$config['key'] => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $ids[] = (int)$row->get($config['key']);
        }

        return $ids;
    }

    /**
     * Ids of the Libraries a member belongs to.
     *
     * @return list<int>
     */
    public function libraryIdsFor(string $memberType, int $memberId): array
    {
        $config = $this->configFor($memberType);
        $ids = [];
        $rows = $this->tables->get($config['join'])->find()
            ->where([$config['key'] => $memberId])
            ->orderBy(['library_id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $ids[] = (int)$row->get('library_id');
        }

        return $ids;
    }

    /**
     * Whether a Library still has any members, which blocks deletion.
     */
    public function hasMembers(Library $library): bool
    {
        foreach (array_keys(self::MEMBER_TYPES) as $memberType) {
            if ($this->memberIds($library, $memberType) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the join configuration for a member kind.
     *
     * @return array{join: string, key: string, table: string}
     * @throws \DomainException When the member kind is unknown.
     */
    private function configFor(string $memberType): array
    {
        if (!$this->isSupportedType($memberType)) {
            throw new DomainException('Unknown library member type.');
        }

        return self::MEMBER_TYPES[$memberType];
    }

    /**
     * Require that the member exists and belongs to the Library owner.
     *
     * @throws \DomainException When the member is missing or owned by someone else.
     */
    private function assertOwnedMember(string $table, int $memberId, int $userId): void
    {
        if (!$this->tables->get($table)->exists(['id' => $memberId, 'user_id' => $userId])) {
            throw new DomainException('Library members must be owned by the library owner.');
        }
    }

    /**
     * Detect unique-constraint violations raised by concurrent inserts.
     */
    private function isUniqueViolation(Throwable $exception): bool
    {
        if ((string)$exception->getCode() === '23505') {
            return true;
        }
        $previous = $exception->getPrevious();

        return $previous instanceof Throwable && $this->isUniqueViolation($previous);
    }
}
