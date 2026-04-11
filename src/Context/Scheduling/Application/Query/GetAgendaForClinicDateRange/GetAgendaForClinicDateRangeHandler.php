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
                BIN_TO_UUID(id) as id,
                BIN_TO_UUID(clinic_id) as clinic_id,
                BIN_TO_UUID(owner_id) as owner_id,
                BIN_TO_UUID(animal_id) as animal_id,
                BIN_TO_UUID(practitioner_user_id) as practitioner_user_id,
                starts_at_utc,
                duration_minutes,
                status,
                reason,
                notes
            FROM scheduling__appointments
            WHERE clinic_id = UUID_TO_BIN(:clinicId)
              AND starts_at_utc >= :fromUtc
              AND starts_at_utc <= :toUtc
        SQL;

        $params = [
            'clinicId' => $query->clinicId,
            'fromUtc'  => $query->fromUtc->format('Y-m-d H:i:s'),
            'toUtc'    => $query->toUtc->format('Y-m-d H:i:s'),
        ];

        if (null !== $query->practitionerUserId) {
            $sql .= ' AND practitioner_user_id = UUID_TO_BIN(:practitionerUserId)';
            $params['practitionerUserId'] = $query->practitionerUserId;
        }

        $sql .= ' ORDER BY starts_at_utc ASC';

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
            practitionerUserId: RowAccessor::nullableString($row, 'practitioner_user_id'),
            startsAtUtc: RowAccessor::string($row, 'starts_at_utc'),
            durationMinutes: RowAccessor::int($row, 'duration_minutes'),
            status: RowAccessor::string($row, 'status'),
            reason: RowAccessor::nullableString($row, 'reason'),
            notes: RowAccessor::nullableString($row, 'notes'),
        );
    }
}
