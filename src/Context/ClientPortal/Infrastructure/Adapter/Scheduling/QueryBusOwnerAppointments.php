<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Scheduling;

use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\GetNextAppointmentForAnimals;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\NextAppointmentItem;
use App\Shared\Application\Bus\QueryBusInterface;

/** Scheduling's GetNextAppointmentForAnimals: never an appointment without animal. */
final readonly class QueryBusOwnerAppointments implements OwnerAppointmentsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment
    {
        if ([] === $animalIds) {
            return null;
        }

        $item = $this->queryBus->ask(new GetNextAppointmentForAnimals($clinicId, $animalIds, $nowUtc));

        if (!$item instanceof NextAppointmentItem) {
            return null;
        }

        return new UpcomingAppointment($item->id, $item->animalId, $item->startsAtUtc, $item->practitionerUserId, $item->category);
    }
}
