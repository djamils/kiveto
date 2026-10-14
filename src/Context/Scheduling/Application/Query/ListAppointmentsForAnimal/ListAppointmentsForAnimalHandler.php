<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAppointmentsForAnimalHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<AnimalAppointmentItem>
     */
    public function __invoke(ListAppointmentsForAnimal $query): array
    {
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.starts_at_utc,
                a.duration_minutes,
                a.status,
                a.reason,
                a.notes
            FROM scheduling__appointments a
            WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
              AND a.animal_id = UUID_TO_BIN(:animalId)
            ORDER BY a.starts_at_utc DESC
            LIMIT :limit
        SQL;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'clinicId' => $query->clinicId,
                'animalId' => $query->animalId,
                'limit'    => max(1, $query->limit),
            ],
            ['limit' => ParameterType::INTEGER],
        );

        return array_map(
            static fn (array $row): AnimalAppointmentItem => new AnimalAppointmentItem(
                id: RowAccessor::string($row, 'id'),
                practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
                startsAtUtc: RowAccessor::string($row, 'starts_at_utc'),
                durationMinutes: RowAccessor::int($row, 'duration_minutes'),
                status: RowAccessor::string($row, 'status'),
                reason: RowAccessor::nullableString($row, 'reason'),
                notes: RowAccessor::nullableString($row, 'notes'),
            ),
            $rows,
        );
    }
}
