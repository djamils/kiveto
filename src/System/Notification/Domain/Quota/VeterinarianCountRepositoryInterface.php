<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Quota;

/**
 * Notification's read-only copy of the daily number of active veterinarians
 * of each clinic, pushed by Clinic (the source of truth).
 */
interface VeterinarianCountRepositoryInterface
{
    /** Creates or replaces the count of that day. */
    public function record(string $clinicId, \DateTimeImmutable $day, int $count): void;

    /** The highest count of the days in [$firstDay, $lastDay], null without any row. */
    public function maxBetween(string $clinicId, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): ?int;

    /** The count of the latest day before $day, null without any row. */
    public function lastKnownBefore(string $clinicId, \DateTimeImmutable $day): ?int;
}
