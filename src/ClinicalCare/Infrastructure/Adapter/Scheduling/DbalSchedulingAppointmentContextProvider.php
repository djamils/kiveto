<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\ClinicalCare\Application\Port\AppointmentContextDTO;
use App\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
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
            clinicId: Uuid::fromBinary($result['clinic_id'])->toRfc4122(),
            linkedWaitingRoomEntryId: $result['waiting_room_entry_id'] 
                ? Uuid::fromBinary($result['waiting_room_entry_id'])->toRfc4122()
                : null,
            ownerId: $result['owner_id'] 
                ? Uuid::fromBinary($result['owner_id'])->toRfc4122()
                : null,
            animalId: $result['animal_id'] 
                ? Uuid::fromBinary($result['animal_id'])->toRfc4122()
                : null,
            arrivalMode: $result['arrival_mode'],
            status: $result['status'],
        );
    }
}
