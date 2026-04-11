<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetAgendaForClinicDateRange implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $fromUtc,
        public \DateTimeImmutable $toUtc,
        public ?string $practitionerUserId = null,
    ) {
    }

    public static function forDay(
        string $clinicId,
        \DateTimeImmutable $date,
        \DateTimeZone $clinicTz,
        ?string $practitionerUserId = null,
    ): self {
        $localStart = $date->setTimezone($clinicTz)->setTime(0, 0, 0);
        $localEnd   = $localStart->modify('+1 day')->modify('-1 second');

        return new self(
            clinicId: $clinicId,
            fromUtc: $localStart->setTimezone(new \DateTimeZone('UTC')),
            toUtc: $localEnd->setTimezone(new \DateTimeZone('UTC')),
            practitionerUserId: $practitionerUserId,
        );
    }

    public static function forWeek(
        string $clinicId,
        \DateTimeImmutable $anchorDate,
        \DateTimeZone $clinicTz,
        ?string $practitionerUserId = null,
    ): self {
        $localAnchor = $anchorDate->setTimezone($clinicTz);
        $localMonday = $localAnchor->modify('monday this week')->setTime(0, 0, 0);
        $localSunday = $localMonday->modify('+7 days')->modify('-1 second');

        return new self(
            clinicId: $clinicId,
            fromUtc: $localMonday->setTimezone(new \DateTimeZone('UTC')),
            toUtc: $localSunday->setTimezone(new \DateTimeZone('UTC')),
            practitionerUserId: $practitionerUserId,
        );
    }
}
