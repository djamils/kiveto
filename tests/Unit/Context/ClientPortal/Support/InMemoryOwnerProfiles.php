<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;

final class InMemoryOwnerProfiles implements OwnerProfileRepositoryInterface
{
    /** @var array<string, OwnerProfile> by user id */
    public array $profiles = [];

    public int $saves = 0;

    public function save(OwnerProfile $profile): void
    {
        $this->profiles[$profile->userId()] = $profile;
        ++$this->saves;
    }

    public function updateIdentity(OwnerProfile $profile): void
    {
        $this->profiles[$profile->userId()] = $profile;
        ++$this->saves;
    }

    public function findByUserId(string $userId): ?OwnerProfile
    {
        return $this->profiles[$userId] ?? null;
    }
}
