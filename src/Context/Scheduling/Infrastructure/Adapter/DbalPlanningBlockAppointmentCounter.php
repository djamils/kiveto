<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPlanningBlockAppointmentCounter implements PlanningBlockAppointmentCounterInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function countActiveInWindow(
        ClinicId $clinicId,
        UserId $staffUserId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
    ): int {
        return $this->count($clinicId, $staffUserId, $windowStartUtc, $windowEndUtc, null, false);
    }

    public function countOccupyingInWindowLocked(
        ClinicId $clinicId,
        UserId $staffUserId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
        ?AppointmentId $excludeAppointmentId = null,
    ): int {
        return $this->count($clinicId, $staffUserId, $windowStartUtc, $windowEndUtc, $excludeAppointmentId, true);
    }

    private function count(
        ClinicId $clinicId,
        UserId $staffUserId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
        ?AppointmentId $excludeAppointmentId,
        bool $currentRead,
    ): int {
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__appointments
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :staffUserId
              AND status IN (:occupying)
              AND starts_at_utc < :windowEndUtc
              AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :windowStartUtc
        SQL;

        $params = [
            'clinicId'       => Uuid::fromString($clinicId->toString())->toBinary(),
            'staffUserId'    => Uuid::fromString($staffUserId->toString())->toBinary(),
            'windowStartUtc' => $windowStartUtc->format('Y-m-d H:i:s'),
            'windowEndUtc'   => $windowEndUtc->format('Y-m-d H:i:s'),
            // Pending requests count towards the capacity too
            'occupying' => AppointmentStatus::OCCUPYING_SQL_VALUES,
        ];

        if (null !== $excludeAppointmentId) {
            // The appointment being moved does not count against its new block
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeAppointmentId->toString())->toBinary();
        }

        if ($currentRead) {
            // Sees bookings committed after this transaction's snapshot
            $sql .= ' FOR SHARE';
        }

        $result = $this->connection->fetchAssociative($sql, $params, ['occupying' => ArrayParameterType::STRING]);

        if (false === $result) {
            return 0;
        }

        return \App\Shared\Infrastructure\Persistence\RowAccessor::int($result, 'cnt');
    }
}
