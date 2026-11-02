<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Service\PlanningBlockOverlap;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Reads the staff member's blocks that can occur between the first and the
 * last date compared, then compares them occurrence by occurrence.
 */
final readonly class DbalPlanningBlockOverlapChecker implements PlanningBlockOverlapCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function hasOverlap(
        ClinicId $clinicId,
        UserId $staffUserId,
        TimeRange $timeRange,
        RecurrenceRule $rule,
        string $todayLocal,
        ?PlanningBlockId $excludeId = null,
    ): bool {
        $overlap = new PlanningBlockOverlap($timeRange, $rule, $todayLocal);
        $first   = $overlap->firstDate();
        $last    = $overlap->lastDate();

        // A recurring block whose `until` is already past occurs no more
        if (null === $first || null === $last) {
            return false;
        }

        $sql = <<<'SQL'
            SELECT date, start_time, end_time, recurrence_rule
            FROM scheduling__planning_blocks
            WHERE clinic_id = :clinicId
              AND staff_user_id = :staffUserId
              AND date <= :lastDate
              AND start_time < :endTime
              AND end_time > :startTime
              AND (date >= :firstDate OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.freq')) <> :none)
        SQL;

        $params = [
            'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
            'staffUserId' => Uuid::fromString($staffUserId->toString())->toBinary(),
            'firstDate'   => $first,
            'lastDate'    => $last,
            'startTime'   => $timeRange->startTime(),
            'endTime'     => $timeRange->endTime(),
            // A recurring block that began earlier may still occur: its `until` is checked in PHP
            'none' => RecurrenceRule::NONE,
        ];

        if (null !== $excludeId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeId->toString())->toBinary();
        }

        foreach ($this->connection->fetchAllAssociative($sql, $params) as $row) {
            $meets = $overlap->meets(
                RowAccessor::string($row, 'date'),
                RowAccessor::string($row, 'start_time'),
                RowAccessor::string($row, 'end_time'),
                RecurrenceRule::fromJson(RowAccessor::string($row, 'recurrence_rule')),
            );

            if ($meets) {
                return true;
            }
        }

        return false;
    }
}
