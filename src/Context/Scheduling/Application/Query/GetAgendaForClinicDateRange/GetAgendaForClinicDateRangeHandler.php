<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAgendaForClinicDateRangeHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<AppointmentItem>
     */
    public function __invoke(GetAgendaForClinicDateRange $query): array
    {
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.clinic_id) as clinic_id,
                BIN_TO_UUID(a.owner_id) as owner_id,
                BIN_TO_UUID(a.animal_id) as animal_id,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.starts_at_utc,
                a.duration_minutes,
                a.status,
                a.reason,
                a.notes,
                CONCAT(c.last_name, ' ', c.first_name) as owner_label,
                (
                    SELECT cm.value
                    FROM client__contact_methods cm
                    WHERE cm.client_id = a.owner_id AND cm.type = 'phone'
                    ORDER BY cm.is_primary DESC
                    LIMIT 1
                ) as owner_phone,
                an.name as animal_label,
                an.species as animal_species,
                u.email as practitioner_label
            FROM scheduling__appointments a
            LEFT JOIN client__clients c ON c.id = a.owner_id
            LEFT JOIN animal__animals an ON an.id = a.animal_id
            LEFT JOIN identity_access__users u ON u.id = a.practitioner_user_id
            WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
              AND a.starts_at_utc >= :fromUtc
              AND a.starts_at_utc <= :toUtc
        SQL;

        $params = [
            'clinicId' => $query->clinicId,
            'fromUtc'  => $query->fromUtc->format('Y-m-d H:i:s'),
            'toUtc'    => $query->toUtc->format('Y-m-d H:i:s'),
        ];

        if (null !== $query->practitionerUserId) {
            $sql .= ' AND a.practitioner_user_id = UUID_TO_BIN(:practitionerUserId)';
            $params['practitionerUserId'] = $query->practitionerUserId;
        }

        $sql .= ' ORDER BY a.starts_at_utc ASC';

        $results = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(fn (array $row): AppointmentItem => $this->hydrateRow($row), $results);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): AppointmentItem
    {
        return new AppointmentItem(
            id: RowAccessor::string($row, 'id'),
            clinicId: RowAccessor::string($row, 'clinic_id'),
            ownerId: RowAccessor::nullableString($row, 'owner_id'),
            animalId: RowAccessor::nullableString($row, 'animal_id'),
            practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
            startsAtUtc: RowAccessor::string($row, 'starts_at_utc'),
            durationMinutes: RowAccessor::int($row, 'duration_minutes'),
            status: RowAccessor::string($row, 'status'),
            reason: RowAccessor::nullableString($row, 'reason'),
            notes: RowAccessor::nullableString($row, 'notes'),
            ownerLabel: RowAccessor::nullableString($row, 'owner_label'),
            ownerPhone: RowAccessor::nullableString($row, 'owner_phone'),
            animalLabel: RowAccessor::nullableString($row, 'animal_label'),
            animalSpecies: RowAccessor::nullableString($row, 'animal_species'),
            practitionerLabel: RowAccessor::nullableString($row, 'practitioner_label'),
        );
    }
}
