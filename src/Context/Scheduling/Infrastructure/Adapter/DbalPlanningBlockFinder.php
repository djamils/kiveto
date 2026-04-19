<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockReadModel;
use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPlanningBlockFinder implements PlanningBlockFinderInterface
{
    public function __construct(
        private Connection $connection,
        private RecurrenceExpander $expander,
        private PlanningBlockAppointmentCounterInterface $counter,
        private ClinicTimezoneResolverInterface $timezoneResolver,
    ) {
    }

    public function findActiveBlockFor(
        ClinicId $clinicId,
        UserId $staffUserId,
        string $localDate,
        string $localStartTime,
        string $localEndTime,
    ): ?PlanningBlockReadModel {
        $sql = <<<'SQL'
            SELECT *
            FROM scheduling__planning_blocks
            WHERE clinic_id = :clinicId
              AND staff_user_id = :staffUserId
              AND date <= :localDate
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL
                  OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :localDate
              )
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
            'staffUserId' => Uuid::fromString($staffUserId->toString())->toBinary(),
            'localDate'   => $localDate,
        ]);

        foreach ($rows as $row) {
            $rule        = RecurrenceRule::fromJson(RowAccessor::string($row, 'recurrence_rule'));
            $occurrences = $this->expander->expand(
                $rule,
                RowAccessor::string($row, 'date'),
                new \DateTimeImmutable($localDate),
                new \DateTimeImmutable($localDate),
            );

            if ([] === $occurrences) {
                continue;
            }

            $rowStartTime = RowAccessor::string($row, 'start_time');
            $rowEndTime   = RowAccessor::string($row, 'end_time');

            if ($rowStartTime <= $localStartTime && $rowEndTime >= $localEndTime) {
                $clinicTz = $this->timezoneResolver->resolveTimezone($clinicId);
                $startUtc = (new \DateTimeImmutable($localDate . ' ' . $rowStartTime, $clinicTz))
                    ->setTimezone(new \DateTimeZone('UTC'))
                ;
                $endUtc = (new \DateTimeImmutable($localDate . ' ' . $rowEndTime, $clinicTz))
                    ->setTimezone(new \DateTimeZone('UTC'))
                ;

                $count = $this->counter->countActiveInWindow($clinicId, $staffUserId, $startUtc, $endUtc);
                $type  = PlanningBlockType::from(RowAccessor::string($row, 'type'));

                return new PlanningBlockReadModel(
                    id: RowAccessor::uuid($row, 'id'),
                    type: $type,
                    capacityPerHour: RowAccessor::int($row, 'capacity_per_hour'),
                    currentAppointmentCount: $count,
                    acceptsAppointments: $type->acceptsAppointments(),
                );
            }
        }

        return null;
    }
}
