<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetAgendaForClinicDay;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAgendaForClinicDayHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return list<AppointmentItem>
     */
    public function __invoke(GetAgendaForClinicDay $query): array
    {
        $startOfDay = $query->date->setTime(0, 0, 0);
        $endOfDay   = $query->date->setTime(23, 59, 59);

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
              AND starts_at_utc >= :startOfDay
              AND starts_at_utc <= :endOfDay
        SQL;

        $params = [
            'clinicId'   => $query->clinicId,
            'startOfDay' => $startOfDay->format('Y-m-d H:i:s'),
            'endOfDay'   => $endOfDay->format('Y-m-d H:i:s'),
        ];

        if (null !== $query->practitionerUserId) {
            $sql .= ' AND practitioner_user_id = UUID_TO_BIN(:practitionerUserId)';
            $params['practitionerUserId'] = $query->practitionerUserId;
        }

        $sql .= ' ORDER BY starts_at_utc ASC';

        $results = $this->connection->fetchAllAssociative($sql, $params);

        return \array_map(
            fn (array $row) => new AppointmentItem(
                id: $row['id'],
                clinicId: $row['clinic_id'],
                ownerId: $row['owner_id'],
                animalId: $row['animal_id'],
                practitionerUserId: $row['practitioner_user_id'],
                startsAtUtc: $row['starts_at_utc'],
                durationMinutes: (int) $row['duration_minutes'],
                status: $row['status'],
                reason: $row['reason'],
                notes: $row['notes'],
            ),
            $results
        );
    }
}
