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

    public function clinicsWithUnannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $this->windows[] = [$from->format('Y-m-d H:i e'), $until->format('Y-m-d')];
        $clinics         = [];

        foreach ($this->candidates($from, $until) as $lot) {
            $clinics[$lot['clinicId']] = true;
        }

        return array_keys($clinics);
    }

    public function unannouncedLotsExpiringBetween(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $this->windows[] = [$from->format('Y-m-d H:i e'), $until->format('Y-m-d')];

        return array_keys(array_filter($this->candidates($from, $until), static fn (array $lot): bool => $lot['clinicId'] === $clinicId));
    }

    public function markAnnounced(array $lotIds, \DateTimeImmutable $at): void
    {
        foreach ($lotIds as $lotId) {
            $this->announced[$lotId] = $at->format(\DateTimeInterface::ATOM);
        }
    }

    /**
     * @return array<string, array{clinicId: string, expiresOn: string}>
     */
    private function candidates(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        return array_filter(
            $this->lots,
            fn (array $lot, string $lotId): bool => !isset($this->announced[$lotId]) && $lot['expiresOn'] >= $from->format('Y-m-d') && $lot['expiresOn'] <= $until->format('Y-m-d'),
            \ARRAY_FILTER_USE_BOTH,
        );
    }
}
