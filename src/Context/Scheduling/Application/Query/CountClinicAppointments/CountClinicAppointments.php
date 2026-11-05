<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\CountClinicAppointments;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Shared\Application\Bus\QueryInterface;

/**
 * How many appointments of the clinic start in the range, the withdrawn ones
 * (cancelled, declined) left out: the agenda's count, without reading the
 * agenda. Answers an int.
 */
final readonly class CountClinicAppointments implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $fromUtc,
        public \DateTimeImmutable $toUtc,
    ) {
    }

    /** The clinic's local day of the date, as the agenda reads it. */
    public static function forDay(string $clinicId, \DateTimeImmutable $date, \DateTimeZone $clinicTz): self
    {
        $day = GetAgendaForClinicDateRange::forDay($clinicId, $date, $clinicTz);

        return new self($clinicId, $day->fromUtc, $day->toUtc);
    }
}
