<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\Context\ClinicalCare\Application\Port\AppointmentContextDTO;
use App\Context\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
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
            clinicId: RowAccessor::uuid($result, 'clinic_id'),
            linkedWaitingRoomEntryId: RowAccessor::nullableUuid($result, 'waiting_room_entry_id'),
            ownerId: RowAccessor::nullableUuid($result, 'owner_id'),
            animalId: RowAccessor::nullableUuid($result, 'animal_id'),
            arrivalMode: RowAccessor::nullableString($result, 'arrival_mode'),
            status: RowAccessor::string($result, 'status'),
        );
    }
}
