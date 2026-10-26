<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Domain\Quota\VeterinarianCountRepositoryInterface;

final class InMemoryVeterinarianCounts implements VeterinarianCountRepositoryInterface
{
    /** @var array<string, array<string, int>> clinic → day (Y-m-d) → count */
    public array $counts = [];

    public function record(string $clinicId, \DateTimeImmutable $day, int $count): void
    {
        $this->counts[$clinicId][$day->format('Y-m-d')] = $count;
    }

    public function maxBetween(string $clinicId, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): ?int
    {
        $values = array_filter(
            $this->counts[$clinicId] ?? [],
            static fn (string $day): bool => $day >= $firstDay->format('Y-m-d') && $day <= $lastDay->format('Y-m-d'),
            \ARRAY_FILTER_USE_KEY,
        );

        return [] === $values ? null : max($values);
    }

    public function lastKnownBefore(string $clinicId, \DateTimeImmutable $day): ?int
    {
        $before = array_filter($this->counts[$clinicId] ?? [], static fn (string $d): bool => $d < $day->format('Y-m-d'), \ARRAY_FILTER_USE_KEY);
        ksort($before);

        return [] === $before ? null : $before[array_key_last($before)];
    }
}
