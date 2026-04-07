<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\ListWaitingRoom;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListWaitingRoomHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<WaitingRoomEntryItem>
     */
    public function __invoke(ListWaitingRoom $query): array
    {
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(id) as id,
                BIN_TO_UUID(clinic_id) as clinic_id,
                origin,
                arrival_mode,
                BIN_TO_UUID(linked_appointment_id) as linked_appointment_id,
                BIN_TO_UUID(owner_id) as owner_id,
                BIN_TO_UUID(animal_id) as animal_id,
                found_animal_description,
                priority,
                triage_notes,
                status,
                arrived_at_utc,
                called_at_utc,
                service_started_at_utc,
                closed_at_utc
            FROM scheduling__waiting_room_entries
            WHERE clinic_id = UUID_TO_BIN(:clinicId)
              AND status IN ('WAITING', 'CALLED', 'IN_SERVICE')
            ORDER BY
                CASE WHEN arrival_mode = 'EMERGENCY' THEN 0 ELSE 1 END ASC,
                priority DESC,
                arrived_at_utc ASC
        SQL;

        $results = $this->connection->fetchAllAssociative($sql, [
            'clinicId' => $query->clinicId,
        ]);

        return array_map(
            fn (array $row) => new WaitingRoomEntryItem(
                id: $row['id'],
                clinicId: $row['clinic_id'],
                origin: $row['origin'],
                arrivalMode: $row['arrival_mode'],
                linkedAppointmentId: $row['linked_appointment_id'],
                ownerId: $row['owner_id'],
                animalId: $row['animal_id'],
                foundAnimalDescription: $row['found_animal_description'],
                priority: (int) $row['priority'],
                triageNotes: $row['triage_notes'],
                status: $row['status'],
                arrivedAtUtc: $row['arrived_at_utc'],
                calledAtUtc: $row['called_at_utc'],
                serviceStartedAtUtc: $row['service_started_at_utc'],
                closedAtUtc: $row['closed_at_utc'],
            ),
            $results
        );
    }
}
