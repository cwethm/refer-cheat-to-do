<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\OwnershipPolicy;
use Cake\TestSuite\TestCase;

class OwnershipPolicyTest extends TestCase
{
    public function testCanAccessReturnsTrueForOwner(): void
    {
        $policy = new OwnershipPolicy();

        $this->assertTrue($policy->canAccess(1, 1));
    }

    public function testCanAccessReturnsFalseForDifferentUser(): void
    {
        $policy = new OwnershipPolicy();

        $this->assertFalse($policy->canAccess(2, 1));
    }

    public function testCanAccessReturnsFalseForAnonymousUser(): void
    {
        $policy = new OwnershipPolicy();

        $this->assertFalse($policy->canAccess(null, 1));
    }
}
