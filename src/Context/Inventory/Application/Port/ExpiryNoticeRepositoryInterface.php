<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * The lots announced « Péremption proche » (each lot once, ever).
 */
interface ExpiryNoticeRepositoryInterface
{
    /**
     * The clinics with lots to announce expiring between $from and $until
     * (inclusive days).
     *
     * @return list<string> clinic ids
     */
    public function clinicsWithUnannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * A clinic's active lots of tracked, active stock items that expire
     * between $from and $until (inclusive days) and were never announced.
     *
     * @return list<string> lot ids
     */
    public function unannouncedLotsExpiringBetween(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * @param list<string> $lotIds
     */
    public function markAnnounced(array $lotIds, \DateTimeImmutable $at): void;
}
