<?php
declare(strict_types=1);

namespace App\Policy;

class OwnershipPolicy
{
    public function canAccess(?int $actorUserId, int $ownerUserId): bool
    {
        return $actorUserId !== null && $actorUserId === $ownerUserId;
    }
}
