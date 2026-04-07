<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Adapter;

use App\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
use Doctrine\DBAL\Connection;

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
        $endsAt = $timeSlot->endsAtUtc();

        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__appointments
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :practitionerUserId
              AND status = 'PLANNED'
              AND (
                  (starts_at_utc < :endsAt AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :startsAt)
              )
        SQL;

        $params = [
            'clinicId'           => $clinicId->toString(),
            'practitionerUserId' => $practitionerUserId->toString(),
            'startsAt'           => $timeSlot->startsAtUtc()->format('Y-m-d H:i:s'),
            'endsAt'             => $endsAt->format('Y-m-d H:i:s'),
        ];

        if (null !== $excludeAppointmentId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeAppointmentId->toString();
        }

        $result = $this->connection->fetchAssociative($sql, $params);

        return ($result['cnt'] ?? 0) > 0;
    }
}
