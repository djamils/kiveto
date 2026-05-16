<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange;

use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ListPlanningBlocksForClinicDateRangeHandler
{
    public function __construct(
        private Connection $connection,
        private RecurrenceExpander $expander,
    ) {
    }

    /**
     * @return list<PlanningBlockView>
     */
    public function __invoke(ListPlanningBlocksForClinicDateRange $query): array
    {
        $sql = <<<'SQL'
            SELECT *,
                   LOWER(BIN_TO_UUID(id)) AS id_str,
                   LOWER(BIN_TO_UUID(clinic_id)) AS clinic_str,
                   LOWER(BIN_TO_UUID(staff_user_id)) AS staff_str
            FROM scheduling__planning_blocks
            WHERE clinic_id = :clinicId
              AND date <= :toDate
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL
                  OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :fromDate
              )
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'clinicId' => Uuid::fromString($query->clinicId)->toBinary(),
            'fromDate' => $query->fromDate,
            'toDate'   => $query->toDate,
        ]);

        $rangeStart = \DateTimeImmutable::createFromFormat('Y-m-d', $query->fromDate)
            ?: new \DateTimeImmutable($query->fromDate);
        $rangeEnd = \DateTimeImmutable::createFromFormat('Y-m-d', $query->toDate)
            ?: new \DateTimeImmutable($query->toDate);

        $views = [];
        foreach ($rows as $row) {
            $rule        = RecurrenceRule::fromJson(RowAccessor::string($row, 'recurrence_rule'));
            $occurrences = $this->expander->expand($rule, RowAccessor::string($row, 'date'), $rangeStart, $rangeEnd);
            $type        = PlanningBlockType::from(RowAccessor::string($row, 'type'));

            foreach ($occurrences as $occDate) {
                $views[] = new PlanningBlockView(
                    id: RowAccessor::string($row, 'id_str'),
                    clinicId: RowAccessor::string($row, 'clinic_str'),
                    staffUserId: RowAccessor::string($row, 'staff_str'),
                    type: $type->value,
                    date: $occDate,
                    startTime: RowAccessor::string($row, 'start_time'),
                    endTime: RowAccessor::string($row, 'end_time'),
                    capacityPerHour: RowAccessor::int($row, 'capacity_per_hour'),
                    recurrenceFreq: $rule->freq(),
                    recurrenceUntil: $rule->until(),
                    note: RowAccessor::nullableString($row, 'note'),
                    acceptsAppointments: $type->acceptsAppointments(),
                    hasCapacityLimit: $type->hasCapacityLimit(),
                );
            }
        }

        usort($views, static function (PlanningBlockView $a, PlanningBlockView $b): int {
            return $a->date <=> $b->date
                ?: $a->startTime <=> $b->startTime
                ?: $a->staffUserId <=> $b->staffUserId;
        });

        return $views;
    }
}
