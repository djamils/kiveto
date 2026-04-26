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
                BIN_TO_UUID(id) as id,
                BIN_TO_UUID(clinic_id) as clinic_id,
                BIN_TO_UUID(linked_admission_id) as linked_admission_id,
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
        );
    }
}
