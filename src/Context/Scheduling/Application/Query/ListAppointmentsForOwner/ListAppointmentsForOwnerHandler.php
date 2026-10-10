<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwner;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAppointmentsForOwnerHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<OwnerAppointmentItem>
     */
    public function __invoke(ListAppointmentsForOwner $query): array
    {
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.starts_at_utc,
                a.duration_minutes,
                a.status,
                a.reason,
                BIN_TO_UUID(a.animal_id) as animal_id,
                an.name as animal_label
            FROM scheduling__appointments a
            LEFT JOIN animal__animals an ON an.id = a.animal_id AND an.clinic_id = a.clinic_id
            WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
              AND a.owner_id = UUID_TO_BIN(:ownerId)
            ORDER BY a.starts_at_utc DESC
            LIMIT :limit
        SQL;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'clinicId' => $query->clinicId,
                'ownerId'  => $query->ownerId,
                'limit'    => max(1, $query->limit),
            ],
            ['limit' => ParameterType::INTEGER],
        );

        return array_map(
            static fn (array $row): OwnerAppointmentItem => new OwnerAppointmentItem(
                id: RowAccessor::string($row, 'id'),
                practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
                startsAtUtc: RowAccessor::string($row, 'starts_at_utc'),
                durationMinutes: RowAccessor::int($row, 'duration_minutes'),
                status: RowAccessor::string($row, 'status'),
                reason: RowAccessor::nullableString($row, 'reason'),
                animalId: RowAccessor::nullableString($row, 'animal_id'),
                animalLabel: RowAccessor::nullableString($row, 'animal_label'),
            ),
            $rows,
        );
    }
}
