<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * What online availability needs from Clinic: whether the clinic is active,
 * its time zone, its opening week and its exceptional closures.
 */
final readonly class ClinicOpeningHours
{
    /**
     * @param array<int, list<array{opensAt: string, closesAt: string}>> $week        keyed by ISO day, 1 (Monday) to 7 (Sunday); `HH:MM` local times
     * @param list<string>                                               $closedDates `YYYY-MM-DD`
     */
    public function __construct(
        public bool $active,
        public \DateTimeZone $timeZone,
        public array $week,
        public array $closedDates,
    ) {
    }

    /**
     * @return list<array{opensAt: string, closesAt: string}> none on a closed day or an exceptional closure
     */
    public function slotsOn(string $localDate): array
    {
        if (\in_array($localDate, $this->closedDates, true)) {
            return [];
        }

        $day = (int) new \DateTimeImmutable($localDate)->format('N');

        return $this->week[$day] ?? [];
    }
}
