<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\PortalLockInterface;

/** Records the keys locked, in order. */
final class RecordingPortalLock implements PortalLockInterface
{
    /** @var list<string> */
    public array $keys = [];

    public function lockAccount(string $userId): void
    {
        $this->keys[] = 'account:' . $userId;
    }

    public function lockPetChanges(string $clinicId, string $petId): void
    {
        $this->keys[] = 'pet-changes:' . $clinicId . ':' . $petId;
    }
}
