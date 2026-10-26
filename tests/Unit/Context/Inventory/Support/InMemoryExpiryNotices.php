<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Support;

use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;

/**
 * Lots by clinic with their expiry day; announced lots are remembered.
 */
final class InMemoryExpiryNotices implements ExpiryNoticeRepositoryInterface
{
    /** @var array<string, array{clinicId: string, expiresOn: string}> lot id => lot */
    public array $lots = [];

    /** @var array<string, string> lot id => announced at */
    public array $announced = [];

    /** @var list<array{string, string}> the windows asked for */
    public array $windows = [];

    public function add(string $lotId, string $clinicId, string $expiresOn): void
    {
        $this->lots[$lotId] = ['clinicId' => $clinicId, 'expiresOn' => $expiresOn];
    }

    public function unannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $this->windows[] = [$from->format('Y-m-d H:i e'), $until->format('Y-m-d')];
        $lots            = [];

        foreach ($this->lots as $lotId => $lot) {
            if (!isset($this->announced[$lotId]) && $lot['expiresOn'] >= $from->format('Y-m-d') && $lot['expiresOn'] <= $until->format('Y-m-d')) {
                $lots[$lot['clinicId']][] = $lotId;
            }
        }

        return $lots;
    }

    public function markAnnounced(array $lotIds, \DateTimeImmutable $at): void
    {
        foreach ($lotIds as $lotId) {
            $this->announced[$lotId] = $at->format(\DateTimeInterface::ATOM);
        }
    }
}
