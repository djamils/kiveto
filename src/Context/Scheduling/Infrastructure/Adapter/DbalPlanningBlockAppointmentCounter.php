<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
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
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__appointments
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :staffUserId
              AND status = 'PLANNED'
              AND starts_at_utc < :windowEndUtc
              AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :windowStartUtc
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'clinicId'       => Uuid::fromString($clinicId->toString())->toBinary(),
            'staffUserId'    => Uuid::fromString($staffUserId->toString())->toBinary(),
            'windowStartUtc' => $windowStartUtc->format('Y-m-d H:i:s'),
            'windowEndUtc'   => $windowEndUtc->format('Y-m-d H:i:s'),
        ]);

        if (false === $result) {
            return 0;
        }

        return \App\Shared\Infrastructure\Persistence\RowAccessor::int($result, 'cnt');
    }
}
