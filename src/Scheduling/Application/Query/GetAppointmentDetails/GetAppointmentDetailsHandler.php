<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetAppointmentDetails;

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
            id: $result['id'],
            clinicId: $result['clinic_id'],
            ownerId: $result['owner_id'],
            animalId: $result['animal_id'],
            practitionerUserId: $result['practitioner_user_id'],
            startsAtUtc: $result['starts_at_utc'],
            durationMinutes: (int) $result['duration_minutes'],
            status: $result['status'],
            reason: $result['reason'],
            notes: $result['notes'],
            serviceStartedAtUtc: $result['service_started_at_utc'],
            createdAtUtc: $result['created_at_utc'],
            updatedAtUtc: $result['updated_at_utc'],
        );
    }
}
