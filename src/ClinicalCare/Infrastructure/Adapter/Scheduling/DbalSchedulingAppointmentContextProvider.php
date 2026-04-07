<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\ClinicalCare\Application\Port\AppointmentContextDTO;
use App\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Shared\Infrastructure\Persistence\DbalRow;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalSchedulingAppointmentContextProvider implements SchedulingAppointmentContextProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getAppointmentContext(AppointmentId $appointmentId): AppointmentContextDTO
    {
        $appointmentBinary = Uuid::fromString($appointmentId->toString())->toBinary();

        $sql = '
            SELECT 
                a.clinic_id,
                a.owner_id,
                a.animal_id,
                a.status,
                w.id as waiting_room_entry_id,
                w.arrival_mode
            FROM scheduling__appointments a
            LEFT JOIN scheduling__waiting_room_entries w ON w.linked_appointment_id = a.id
            WHERE a.id = :appointmentId
        ';

        $result = $this->connection->fetchAssociative($sql, [
            'appointmentId' => $appointmentBinary,
        ]);

        if (false === $result) {
            throw new \DomainException('Appointment not found');
        }

        return new AppointmentContextDTO(
            clinicId: DbalRow::uuid($result, 'clinic_id'),
            linkedWaitingRoomEntryId: DbalRow::nullableUuid($result, 'waiting_room_entry_id'),
            ownerId: DbalRow::nullableUuid($result, 'owner_id'),
            animalId: DbalRow::nullableUuid($result, 'animal_id'),
            arrivalMode: DbalRow::nullableString($result, 'arrival_mode'),
            status: DbalRow::string($result, 'status'),
        );
    }
}
