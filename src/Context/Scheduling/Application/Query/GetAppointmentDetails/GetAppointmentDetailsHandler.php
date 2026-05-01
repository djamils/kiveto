<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentDetails;

use App\Shared\Infrastructure\Persistence\RowAccessor;
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
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.clinic_id) as clinic_id,
                BIN_TO_UUID(a.linked_admission_id) as linked_admission_id,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.starts_at_utc,
                a.duration_minutes,
                a.status,
                a.reason,
                a.notes,
                a.service_started_at_utc,
                a.created_at_utc,
                a.updated_at_utc,
                BIN_TO_UUID(a.owner_id) as owner_id,
                BIN_TO_UUID(a.animal_id) as animal_id,
                CONCAT(c.first_name, ' ', c.last_name) as owner_label,
                an.name as animal_label
            FROM scheduling__appointments a
            LEFT JOIN client__clients c ON c.id = a.owner_id
            LEFT JOIN animal__animals an ON an.id = a.animal_id
            WHERE a.id = UUID_TO_BIN(:appointmentId)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'appointmentId' => $query->appointmentId,
        ]);

        if (false === $result) {
            return null;
        }

        return new AppointmentDetails(
            id: RowAccessor::string($result, 'id'),
            clinicId: RowAccessor::string($result, 'clinic_id'),
            linkedAdmissionId: RowAccessor::nullableString($result, 'linked_admission_id'),
            practitionerUserId: RowAccessor::string($result, 'practitioner_user_id'),
            startsAtUtc: RowAccessor::string($result, 'starts_at_utc'),
            durationMinutes: RowAccessor::int($result, 'duration_minutes'),
            status: RowAccessor::string($result, 'status'),
            reason: RowAccessor::nullableString($result, 'reason'),
            notes: RowAccessor::nullableString($result, 'notes'),
            serviceStartedAtUtc: RowAccessor::nullableString($result, 'service_started_at_utc'),
            createdAtUtc: RowAccessor::string($result, 'created_at_utc'),
            updatedAtUtc: RowAccessor::string($result, 'updated_at_utc'),
            ownerId: RowAccessor::nullableString($result, 'owner_id'),
            animalId: RowAccessor::nullableString($result, 'animal_id'),
            ownerLabel: RowAccessor::nullableString($result, 'owner_label'),
            animalLabel: RowAccessor::nullableString($result, 'animal_label'),
        );
    }
}
