<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListWaitingRoom;

use App\Shared\Infrastructure\Persistence\RowAccessor;
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

        return array_map(fn (array $row): WaitingRoomEntryItem => $this->hydrateRow($row), $results);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): WaitingRoomEntryItem
    {
        return new WaitingRoomEntryItem(
            id: RowAccessor::string($row, 'id'),
            clinicId: RowAccessor::string($row, 'clinic_id'),
            origin: RowAccessor::string($row, 'origin'),
            arrivalMode: RowAccessor::string($row, 'arrival_mode'),
            linkedAppointmentId: RowAccessor::nullableString($row, 'linked_appointment_id'),
            ownerId: RowAccessor::nullableString($row, 'owner_id'),
            animalId: RowAccessor::nullableString($row, 'animal_id'),
            foundAnimalDescription: RowAccessor::nullableString($row, 'found_animal_description'),
            priority: RowAccessor::int($row, 'priority'),
            triageNotes: RowAccessor::nullableString($row, 'triage_notes'),
            status: RowAccessor::string($row, 'status'),
            arrivedAtUtc: RowAccessor::string($row, 'arrived_at_utc'),
            calledAtUtc: RowAccessor::nullableString($row, 'called_at_utc'),
            serviceStartedAtUtc: RowAccessor::nullableString($row, 'service_started_at_utc'),
            closedAtUtc: RowAccessor::nullableString($row, 'closed_at_utc'),
        );
    }
}
