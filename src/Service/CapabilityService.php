<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\CapabilityGrant;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Query\SelectQuery;
use DomainException;
use Throwable;

/**
 * Single authoritative capability evaluation path.
 *
 * The model is default deny. A capability is allowed only when the subject owns the resource or an
 * explicit, unrevoked grant names exactly that capability on exactly that resource. Capabilities are
 * never inferred from one another, and Library membership never contributes to a decision.
 */
class CapabilityService
{
    public const CAP_DISCOVER = 'discover';
    public const CAP_READ = 'read';
    public const CAP_REFERENCE = 'reference';
    public const CAP_QUERY = 'query';
    public const CAP_SUGGEST = 'suggest';
    public const CAP_SEND_TASK = 'send_task';
    public const CAP_CALLBACK = 'callback';
    public const CAP_CONTRIBUTE = 'contribute';
    public const CAP_EDIT = 'edit';
    public const CAP_ARCHIVE = 'archive';
    public const CAP_DELETE = 'delete';
    public const CAP_MANAGE_PERMISSIONS = 'manage_permissions';

    public const RESOURCE_PROJECT = 'project';
    public const RESOURCE_NOTEBOOK = 'notebook';
    public const RESOURCE_LIBRARY = 'library';

    /**
     * The complete capability vocabulary. Anything outside this list is an unknown capability.
     *
     * @var list<string>
     */
    public const CAPABILITIES = [
        self::CAP_DISCOVER,
        self::CAP_READ,
        self::CAP_REFERENCE,
        self::CAP_QUERY,
        self::CAP_SUGGEST,
        self::CAP_SEND_TASK,
        self::CAP_CALLBACK,
        self::CAP_CONTRIBUTE,
        self::CAP_EDIT,
        self::CAP_ARCHIVE,
        self::CAP_DELETE,
        self::CAP_MANAGE_PERMISSIONS,
    ];

    /**
     * Resource kinds that can carry grants, mapped to the table that owns them.
     *
     * @var array<string, string>
     */
    private const RESOURCE_TABLES = [
        self::RESOURCE_PROJECT => 'Projects',
        self::RESOURCE_NOTEBOOK => 'Notebooks',
        self::RESOURCE_LIBRARY => 'Libraries',
    ];

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve resources and grants.
     */
    public function __construct(private LocatorInterface $tables)
    {
    }

    /**
     * Whether the capability name belongs to the known vocabulary.
     */
    public function isKnownCapability(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    /**
     * Whether the resource kind is one that carries grants.
     */
    public function isKnownResourceType(string $resourceType): bool
    {
        return array_key_exists($resourceType, self::RESOURCE_TABLES);
    }

    /**
     * Decide whether a subject may perform a capability on a resource.
     *
     * Default deny: every path that is not an ownership match or an exact active grant returns false.
     */
    public function allows(int $subjectUserId, string $capability, string $resourceType, int $resourceId): bool
    {
        if (!$this->isKnownCapability($capability) || !$this->isKnownResourceType($resourceType)) {
            return false;
        }
        if ($subjectUserId < 1 || $resourceId < 1) {
            return false;
        }
        if ($this->ownerIdFor($resourceType, $resourceId) === $subjectUserId) {
            return true;
        }

        return $this->activeGrantQuery($subjectUserId, $resourceType, $resourceId)
            ->where(['capability' => $capability])
            ->count() > 0;
    }

    /**
     * The capabilities a subject currently holds on a resource, excluding ownership.
     *
     * @return list<string>
     */
    public function grantedCapabilities(int $subjectUserId, string $resourceType, int $resourceId): array
    {
        if (!$this->isKnownResourceType($resourceType) || $subjectUserId < 1 || $resourceId < 1) {
            return [];
        }

        $capabilities = [];
        $rows = $this->activeGrantQuery($subjectUserId, $resourceType, $resourceId)
            ->orderBy(['capability' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $capabilities[] = (string)$row->get('capability');
        }

        return $capabilities;
    }

    /**
     * Whether a subject may administer grants on a resource.
     *
     * Owners always may. Others need an explicit `manage_permissions` grant, which by itself confers
     * no other capability.
     */
    public function canManagePermissions(int $subjectUserId, string $resourceType, int $resourceId): bool
    {
        return $this->allows($subjectUserId, self::CAP_MANAGE_PERMISSIONS, $resourceType, $resourceId);
    }

    /**
     * Resolve the owning user id of a resource, or null when the resource does not exist.
     */
    public function ownerIdFor(string $resourceType, int $resourceId): ?int
    {
        if (!$this->isKnownResourceType($resourceType) || $resourceId < 1) {
            return null;
        }

        $row = $this->tables->get(self::RESOURCE_TABLES[$resourceType])->find()
            ->select(['user_id'])
            ->where(['id' => $resourceId])
            ->first();
        if (!$row instanceof EntityInterface) {
            return null;
        }

        return (int)$row->get('user_id');
    }

    /**
     * Create an explicit grant.
     *
     * @throws \DomainException When the request is invalid, would escalate privileges, or duplicates
     *   an existing active grant.
     */
    public function grant(
        int $grantorUserId,
        int $subjectUserId,
        string $capability,
        string $resourceType,
        int $resourceId,
    ): CapabilityGrant {
        if (!$this->isKnownCapability($capability)) {
            throw new DomainException('Unknown capability.');
        }
        if (!$this->isKnownResourceType($resourceType)) {
            throw new DomainException('Unknown resource type.');
        }
        if ($this->ownerIdFor($resourceType, $resourceId) === null) {
            throw new DomainException('Unknown resource.');
        }
        if (!$this->canManagePermissions($grantorUserId, $resourceType, $resourceId)) {
            throw new DomainException('Not permitted to manage permissions for this resource.');
        }
        if ($subjectUserId === $grantorUserId) {
            throw new DomainException('A subject cannot grant capabilities to itself.');
        }
        if (!$this->tables->get('Users')->exists(['id' => $subjectUserId])) {
            throw new DomainException('Unknown subject.');
        }

        $grants = $this->tables->get('CapabilityGrants');
        $existing = $grants->find()
            ->where([
                'subject_user_id' => $subjectUserId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'capability' => $capability,
            ])
            ->first();
        if ($existing !== null) {
            if ($existing->get('revoked_at') === null) {
                throw new DomainException('This capability is already granted.');
            }
            $existing->set('revoked_at', null);
            $existing->set('grantor_user_id', $grantorUserId);
            $grants->saveOrFail($existing);

            /** @var \App\Model\Entity\CapabilityGrant $existing */
            return $existing;
        }

        /** @var \App\Model\Entity\CapabilityGrant $grant */
        $grant = $grants->newEntity([
            'subject_user_id' => $subjectUserId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'capability' => $capability,
        ]);
        $grant->set('grantor_user_id', $grantorUserId);
        if ($grant->hasErrors()) {
            throw new DomainException('Invalid capability grant.');
        }

        try {
            $saved = $grants->save($grant);
        } catch (Throwable $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new DomainException('This capability is already granted.');
            }

            throw $exception;
        }
        if ($saved === false) {
            throw new DomainException('Invalid capability grant.');
        }

        return $grant;
    }

    /**
     * Revoke an existing grant. Revocation takes effect immediately.
     *
     * @throws \DomainException When the caller may not manage the resource or no active grant exists.
     */
    public function revoke(int $actorUserId, int $grantId): CapabilityGrant
    {
        $grants = $this->tables->get('CapabilityGrants');
        /** @var \App\Model\Entity\CapabilityGrant|null $grant */
        $grant = $grants->find()->where(['id' => $grantId])->first();
        if ($grant === null || !$grant->isActive()) {
            throw new DomainException('Unknown capability grant.');
        }
        if (!$this->canManagePermissions($actorUserId, $grant->resource_type, (int)$grant->resource_id)) {
            throw new DomainException('Not permitted to manage permissions for this resource.');
        }

        $grant->set('revoked_at', new DateTime());
        $grants->saveOrFail($grant);

        return $grant;
    }

    /**
     * List the active grants on a resource.
     *
     * @return list<\App\Model\Entity\CapabilityGrant>
     */
    public function grantsForResource(string $resourceType, int $resourceId): array
    {
        if (!$this->isKnownResourceType($resourceType) || $resourceId < 1) {
            return [];
        }

        $items = [];
        $rows = $this->tables->get('CapabilityGrants')->find()
            ->where([
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'revoked_at IS' => null,
            ])
            ->orderBy(['subject_user_id' => 'ASC', 'capability' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\CapabilityGrant $row */
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Base query for the active grants held by a subject on a resource.
     *
     * @return \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface>
     */
    private function activeGrantQuery(
        int $subjectUserId,
        string $resourceType,
        int $resourceId,
    ): SelectQuery {
        return $this->tables->get('CapabilityGrants')->find()
            ->where([
                'subject_user_id' => $subjectUserId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'revoked_at IS' => null,
            ]);
    }

    /**
     * Detect unique-constraint violations raised by concurrent grants.
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
