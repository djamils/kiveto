<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * The lots announced « Péremption proche » (each lot once, ever).
 */
interface ExpiryNoticeRepositoryInterface
{
    /**
     * Active lots of tracked, active stock items that expire between $from
     * and $until (inclusive days) and were never announced, by clinic.
     *
     * @return array<string, list<string>> clinic id => lot ids
     */
    public function unannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array;

    /**
     * @param list<string> $lotIds
     */
    public function markAnnounced(array $lotIds, \DateTimeImmutable $at): void;
}
