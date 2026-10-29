<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Profile;

interface OwnerProfileRepositoryInterface
{
    public function save(OwnerProfile $profile): void;

    public function findByUserId(string $userId): ?OwnerProfile;
}
