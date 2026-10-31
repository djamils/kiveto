<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicNamesInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;

/** Clinic's GetClinic: its name and the city of its letterhead. */
final readonly class QueryBusClinicNames implements ClinicNamesInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function clinicOf(string $clinicId): ClinicLabel
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        if (!$clinic instanceof ClinicDto) {
            return new ClinicLabel('', null);
        }

        $city = $clinic->letterhead?->city;

        return new ClinicLabel($clinic->name, null !== $city && '' !== mb_trim($city) ? $city : null, $clinic->timeZone);
    }
}
