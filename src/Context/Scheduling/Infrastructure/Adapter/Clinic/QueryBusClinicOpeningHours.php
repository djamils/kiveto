<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\ClinicOpeningHoursInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's `GetClinicSettings`: status, time zone, opening week and
 * exceptional closures, in one read.
 */
final readonly class QueryBusClinicOpeningHours implements ClinicOpeningHoursInterface
{
    private const string ACTIVE = 'active';

    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function of(ClinicId $clinicId): ?ClinicOpeningHours
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId->toString()));

        if (!$settings instanceof ClinicSettingsView) {
            return null;
        }

        return new ClinicOpeningHours(
            active: self::ACTIVE === $settings->status,
            timeZone: new \DateTimeZone($settings->timeZone),
            week: $settings->week,
            closedDates: array_column($settings->closures, 'date'),
        );
    }
}
