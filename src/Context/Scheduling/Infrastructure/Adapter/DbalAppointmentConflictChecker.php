<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
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

        // clinic_id, practitioner_user_id and id are stored as BINARY(16) by Doctrine's UuidType.
        $params = [
            'clinicId'           => Uuid::fromString($clinicId->toString())->toBinary(),
            'practitionerUserId' => Uuid::fromString($practitionerUserId->toString())->toBinary(),
            'startsAt'           => $timeSlot->startsAtUtc()->format('Y-m-d H:i:s'),
            'endsAt'             => $endsAt->format('Y-m-d H:i:s'),
        ];

        if (null !== $excludeAppointmentId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeAppointmentId->toString())->toBinary();
        }

        $result = $this->connection->fetchAssociative($sql, $params);

        return ($result['cnt'] ?? 0) > 0;
    }
}
