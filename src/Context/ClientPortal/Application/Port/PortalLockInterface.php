<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Transactional mutexes, held until the surrounding transaction ends.
 * Taken first, before the counts a limit is checked on, so two concurrent
 * writes of the same owner account (or on the same pet) are serialised
 * instead of both passing the limit, or deadlocking on the gap locks of
 * `FOR UPDATE` reads that found nothing.
 */
interface PortalLockInterface
{
    /** Every write of an owner account that counts or looks up its own pending rows. */
    public function lockAccount(string $userId): void;

    /** The change requests of a pet, whoever of its owners submits them. */
    public function lockPetChanges(string $clinicId, string $petId): void;
}
