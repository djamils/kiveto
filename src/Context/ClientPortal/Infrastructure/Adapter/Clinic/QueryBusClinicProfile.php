<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentClinic;

/** Clinic's GetClinic: its name, time zone and letterhead. */
final readonly class QueryBusClinicProfile implements ClinicProfileInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function clinicOf(string $clinicId): ClinicLabel
    {
        $clinic = $this->clinic($clinicId);

        if (null === $clinic) {
            return new ClinicLabel('', null);
        }

        $city = $clinic->letterhead?->city;

        return new ClinicLabel($clinic->name, null !== $city && '' !== mb_trim($city) ? $city : null, $clinic->timeZone);
    }

    public function documentHeaderOf(string $clinicId): ?DocumentClinic
    {
        return $this->clinic($clinicId)?->toDocumentClinic();
    }

    private function clinic(string $clinicId): ?ClinicDto
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        return $clinic instanceof ClinicDto ? $clinic : null;
    }
}
