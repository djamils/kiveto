<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Inventory\Application\Port\ClinicTimeZoneInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's GetClinic; Europe/Paris for a clinic it does not know.
 */
final readonly class QueryBusClinicTimeZone implements ClinicTimeZoneInterface
{
    private const string FALLBACK = 'Europe/Paris';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function of(string $clinicId): \DateTimeZone
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        return new \DateTimeZone($clinic instanceof ClinicDto ? $clinic->timeZone : self::FALLBACK);
    }
}
