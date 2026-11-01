<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAppointmentConflictChecker implements AppointmentConflictCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function hasOverlap(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool {
        return $this->overlaps($clinicId, $practitionerUserId, $timeSlot, $excludeAppointmentId, false);
    }

    public function hasOverlapLocked(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool {
        return $this->overlaps($clinicId, $practitionerUserId, $timeSlot, $excludeAppointmentId, true);
    }

    private function overlaps(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId,
        bool $currentRead,
    ): bool {
        $endsAt = $timeSlot->endsAtUtc();

        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__appointments
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :practitionerUserId
              AND status IN (:occupying)
              AND (
                  (starts_at_utc < :endsAt AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :startsAt)
              )
        SQL;

        // clinic_id, practitioner_user_id and id are stored as BINARY(16) by Doctrine's UuidType.
        $params = [
            'clinicId'           => Uuid::fromString($clinicId->toString())->toBinary(),
            'practitionerUserId' => Uuid::fromString($practitionerUserId->toString())->toBinary(),
            'startsAt'           => $timeSlot->startsAtUtc()->format('Y-m-d H:i:s'),
            'endsAt'             => $endsAt->format('Y-m-d H:i:s'),
            // Pending requests hold their slot too
            'occupying' => AppointmentStatus::OCCUPYING_SQL_VALUES,
        ];

        if (null !== $excludeAppointmentId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeAppointmentId->toString())->toBinary();
        }

        if ($currentRead) {
            // Sees bookings committed after this transaction's snapshot
            $sql .= ' FOR SHARE';
        }

        $result = $this->connection->fetchAssociative($sql, $params, ['occupying' => ArrayParameterType::STRING]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
