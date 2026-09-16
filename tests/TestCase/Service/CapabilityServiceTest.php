<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CapabilityService;
use App\Service\LibraryMembershipService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;

class CapabilityServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.Notebooks',
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
        'app.CapabilityGrants',
    ];

    private CapabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CapabilityService(TableRegistry::getTableLocator());
    }

    /**
     * @return list<array{string}>
     */
    public static function capabilityProvider(): array
    {
        return array_map(
            static fn(string $capability): array => [$capability],
            CapabilityService::CAPABILITIES,
        );
    }

    #[DataProvider('capabilityProvider')]
    public function testAbsenceOfGrantDeniesEveryCapability(string $capability): void
    {
        $this->assertFalse(
            $this->service->allows(2, $capability, CapabilityService::RESOURCE_PROJECT, 200),
        );
    }

    #[DataProvider('capabilityProvider')]
    public function testExplicitGrantAllowsExactlyThatCapability(string $capability): void
    {
        $this->service->grant(1, 2, $capability, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertTrue(
            $this->service->allows(2, $capability, CapabilityService::RESOURCE_PROJECT, 200),
        );

        foreach (CapabilityService::CAPABILITIES as $other) {
            if ($other === $capability) {
                continue;
            }
            $this->assertFalse(
                $this->service->allows(2, $other, CapabilityService::RESOURCE_PROJECT, 200),
                sprintf('`%s` must not imply `%s`.', $capability, $other),
            );
        }
    }

    public function testOwnerHoldsEveryCapabilityOnOwnedResource(): void
    {
        foreach (CapabilityService::CAPABILITIES as $capability) {
            $this->assertTrue(
                $this->service->allows(1, $capability, CapabilityService::RESOURCE_PROJECT, 200),
            );
        }
    }

    public function testGrantOnOneResourceDoesNotApplyToAnother(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertFalse(
            $this->service->allows(2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_NOTEBOOK, 400),
        );
        $this->assertFalse(
            $this->service->allows(2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_LIBRARY, 600),
        );
    }

    public function testGrantAppliesOnlyToTheNamedSubject(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertFalse(
            $this->service->allows(3, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200),
        );
    }

    public function testRevokeImmediatelyDeniesTheCapability(): void
    {
        $grant = $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
        $this->assertTrue(
            $this->service->allows(2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200),
        );

        $this->service->revoke(1, (int)$grant->id);

        $this->assertFalse(
            $this->service->allows(2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200),
        );
        $this->assertSame([], $this->service->grantedCapabilities(2, CapabilityService::RESOURCE_PROJECT, 200));
    }

    public function testRevokedGrantCanBeReissued(): void
    {
        $grant = $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
        $this->service->revoke(1, (int)$grant->id);

        $reissued = $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertSame((int)$grant->id, (int)$reissued->id);
        $this->assertTrue(
            $this->service->allows(2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200),
        );
    }

    public function testDuplicateActiveGrantIsRejected(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already granted');
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
    }

    public function testUnknownCapabilityIsRejected(): void
    {
        $this->assertFalse($this->service->isKnownCapability('superuser'));
        $this->assertFalse($this->service->allows(2, 'superuser', CapabilityService::RESOURCE_PROJECT, 200));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown capability.');
        $this->service->grant(1, 2, 'superuser', CapabilityService::RESOURCE_PROJECT, 200);
    }

    public function testUnknownResourceTypeIsRejected(): void
    {
        $this->assertFalse($this->service->isKnownResourceType('todo'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown resource type.');
        $this->service->grant(1, 2, CapabilityService::CAP_READ, 'todo', 200);
    }

    public function testUnknownResourceIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown resource.');
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 9999);
    }

    public function testUnknownSubjectIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown subject.');
        $this->service->grant(1, 9999, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
    }

    public function testNonManagerCannotGrant(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted');
        $this->service->grant(2, 3, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
    }

    public function testSelfEscalationIsRejected(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_MANAGE_PERMISSIONS, CapabilityService::RESOURCE_PROJECT, 200);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot grant capabilities to itself');
        $this->service->grant(2, 2, CapabilityService::CAP_EDIT, CapabilityService::RESOURCE_PROJECT, 200);
    }

    public function testManagePermissionsDoesNotImplyDeleteOrEdit(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_MANAGE_PERMISSIONS, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertTrue($this->service->canManagePermissions(2, CapabilityService::RESOURCE_PROJECT, 200));
        $this->assertFalse(
            $this->service->allows(2, CapabilityService::CAP_DELETE, CapabilityService::RESOURCE_PROJECT, 200),
        );
        $this->assertFalse(
            $this->service->allows(2, CapabilityService::CAP_EDIT, CapabilityService::RESOURCE_PROJECT, 200),
        );
    }

    public function testManagerCanDelegateButNotToThemselves(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_MANAGE_PERMISSIONS, CapabilityService::RESOURCE_PROJECT, 200);

        $delegated = $this->service->grant(
            2,
            3,
            CapabilityService::CAP_READ,
            CapabilityService::RESOURCE_PROJECT,
            200,
        );

        $this->assertSame(2, (int)$delegated->grantor_user_id);
        $this->assertTrue(
            $this->service->allows(3, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200),
        );
    }

    public function testRevokeRequiresManagementAuthority(): void
    {
        $grant = $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted');
        $this->service->revoke(2, (int)$grant->id);
    }

    public function testRevokingTwiceIsRejected(): void
    {
        $grant = $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
        $this->service->revoke(1, (int)$grant->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown capability grant.');
        $this->service->revoke(1, (int)$grant->id);
    }

    public function testLibraryMembershipDoesNotImplyAnyCapability(): void
    {
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $membership = new LibraryMembershipService(TableRegistry::getTableLocator());
        /** @var \App\Model\Entity\Library $library */
        $library = $libraries->get(600);
        $membership->addMember($library, LibraryMembershipService::MEMBER_PROJECT, 200);
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_LIBRARY, 600);

        foreach (CapabilityService::CAPABILITIES as $capability) {
            $this->assertFalse(
                $this->service->allows(2, $capability, CapabilityService::RESOURCE_PROJECT, 200),
                sprintf('Library membership must not confer `%s` on a member project.', $capability),
            );
        }
    }

    public function testGrantedCapabilitiesExcludesOwnership(): void
    {
        $this->service->grant(1, 2, CapabilityService::CAP_READ, CapabilityService::RESOURCE_PROJECT, 200);
        $this->service->grant(1, 2, CapabilityService::CAP_QUERY, CapabilityService::RESOURCE_PROJECT, 200);

        $this->assertSame(
            [CapabilityService::CAP_QUERY, CapabilityService::CAP_READ],
            $this->service->grantedCapabilities(2, CapabilityService::RESOURCE_PROJECT, 200),
        );
        $this->assertSame([], $this->service->grantedCapabilities(1, CapabilityService::RESOURCE_PROJECT, 200));
    }

    public function testInvalidIdentifiersAreDenied(): void
    {
        $this->assertFalse($this->service->allows(0, CapabilityService::CAP_READ, 'project', 200));
        $this->assertFalse($this->service->allows(1, CapabilityService::CAP_READ, 'project', 0));
        $this->assertNull($this->service->ownerIdFor('project', 0));
        $this->assertNull($this->service->ownerIdFor('todo', 200));
    }

    public function testDatabaseRejectsUnknownCapabilityValue(): void
    {
        $this->expectExceptionMessageMatches('/capability/');
        TableRegistry::getTableLocator()->get('CapabilityGrants')->getConnection()->execute(
            'INSERT INTO capability_grants (subject_user_id, grantor_user_id, resource_type, resource_id, capability) '
            . "VALUES (2, 1, 'project', 200, 'superuser')",
        );
    }

    public function testDatabaseRejectsDuplicateGrantRows(): void
    {
        $connection = TableRegistry::getTableLocator()->get('CapabilityGrants')->getConnection();
        $sql = 'INSERT INTO capability_grants (subject_user_id, grantor_user_id, resource_type, resource_id, '
            . "capability) VALUES (2, 1, 'project', 200, 'read')";
        $connection->execute($sql);

        $this->expectExceptionMessageMatches('/duplicate key|unique/i');
        $connection->execute($sql);
    }
}
