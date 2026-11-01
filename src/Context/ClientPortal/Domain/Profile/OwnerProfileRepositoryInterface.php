<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Profile;

interface OwnerProfileRepositoryInterface
{
    /** Inserts a profile; on an existing one, only the terms acceptance moves. */
    public function save(OwnerProfile $profile): void;

    /** Writes the name and the address of an existing profile (the owner's own edit). */
    public function updateIdentity(OwnerProfile $profile): void;

    public function findByUserId(string $userId): ?OwnerProfile;
}
