<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\Scheduling;

use App\Context\Clinic\Application\Port\ClinicAppointmentsInterface;
use App\Context\Scheduling\Application\Query\CountClinicAppointments\CountClinicAppointments;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Counts the clinic's appointments over a window no clinic outlives.
 */
final readonly class QueryBusClinicAppointments implements ClinicAppointmentsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function hasAny(string $clinicId): bool
    {
        $count = $this->queryBus->ask(new CountClinicAppointments(
            $clinicId,
            new \DateTimeImmutable('2000-01-01 00:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2100-01-01 00:00:00', new \DateTimeZone('UTC')),
        ));

        return \is_int($count) && $count > 0;
    }
}
