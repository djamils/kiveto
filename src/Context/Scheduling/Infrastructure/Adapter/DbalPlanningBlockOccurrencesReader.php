<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrencesReaderInterface;
use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPlanningBlockOccurrencesReader implements PlanningBlockOccurrencesReaderInterface
{
    public function __construct(
        private Connection $connection,
        private RecurrenceExpander $expander,
    ) {
    }

    public function occurrencesBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        string $fromDate,
        string $toDate,
        bool $currentRead = false,
    ): array {
        $sql = <<<'SQL'
            SELECT type, date, start_time, end_time, capacity_per_hour, recurrence_rule
            FROM scheduling__planning_blocks
            WHERE clinic_id = :clinicId
              AND staff_user_id = :staffUserId
              AND date <= :toDate
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL
                  OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :fromDate
              )
        SQL;

        if ($currentRead) {
            // Sees blocks changed after this transaction's snapshot
            $sql .= ' FOR SHARE';
        }

        $rows = $this->connection->fetchAllAssociative($sql, [
            'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
            'staffUserId' => Uuid::fromString($practitionerUserId->toString())->toBinary(),
            'fromDate'    => $fromDate,
            'toDate'      => $toDate,
        ]);

        $occurrences = [];

        foreach ($rows as $row) {
            $dates = $this->expander->expand(
                RecurrenceRule::fromJson(RowAccessor::string($row, 'recurrence_rule')),
                RowAccessor::string($row, 'date'),
                new \DateTimeImmutable($fromDate),
                new \DateTimeImmutable($toDate),
            );

            foreach ($dates as $date) {
                $occurrences[] = new PlanningBlockOccurrence(
                    date: $date,
                    startTime: RowAccessor::string($row, 'start_time'),
                    endTime: RowAccessor::string($row, 'end_time'),
                    type: PlanningBlockType::from(RowAccessor::string($row, 'type')),
                    capacityPerHour: RowAccessor::int($row, 'capacity_per_hour'),
                );
            }
        }

        usort(
            $occurrences,
            static fn (PlanningBlockOccurrence $a, PlanningBlockOccurrence $b): int => [$a->date, $a->startTime] <=> [$b->date, $b->startTime],
        );

        return $occurrences;
    }
}
