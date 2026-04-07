<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetAppointmentDetails;

use App\Shared\Infrastructure\Persistence\DbalRow;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAppointmentDetailsHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(GetAppointmentDetails $query): ?AppointmentDetails
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
                notes,
                service_started_at_utc,
                created_at_utc,
                updated_at_utc
            FROM scheduling__appointments
            WHERE id = UUID_TO_BIN(:appointmentId)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'appointmentId' => $query->appointmentId,
        ]);

        if (false === $result) {
            return null;
        }

        return new AppointmentDetails(
            id: DbalRow::string($result, 'id'),
            clinicId: DbalRow::string($result, 'clinic_id'),
            ownerId: DbalRow::nullableString($result, 'owner_id'),
            animalId: DbalRow::nullableString($result, 'animal_id'),
            practitionerUserId: DbalRow::nullableString($result, 'practitioner_user_id'),
            startsAtUtc: DbalRow::string($result, 'starts_at_utc'),
            durationMinutes: DbalRow::int($result, 'duration_minutes'),
            status: DbalRow::string($result, 'status'),
            reason: DbalRow::nullableString($result, 'reason'),
            notes: DbalRow::nullableString($result, 'notes'),
            serviceStartedAtUtc: DbalRow::nullableString($result, 'service_started_at_utc'),
            createdAtUtc: DbalRow::string($result, 'created_at_utc'),
            updatedAtUtc: DbalRow::string($result, 'updated_at_utc'),
        );
    }
}
