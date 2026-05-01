<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetAgendaForClinicDateRangeHandler
{
    public function __construct(
        private Connection $connection,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private RecurrenceExpander $expander,
    ) {
    }

    /**
     * @return list<AppointmentItem>
     */
    public function __invoke(GetAgendaForClinicDateRange $query): array
    {
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.clinic_id) as clinic_id,
                BIN_TO_UUID(a.linked_admission_id) as linked_admission_id,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.starts_at_utc,
                a.duration_minutes,
                a.status,
                a.reason,
                a.notes,
                u.email as practitioner_label,
                BIN_TO_UUID(a.owner_id) as owner_id,
                BIN_TO_UUID(a.animal_id) as animal_id,
                CONCAT(c.first_name, ' ', c.last_name) as owner_label,
                an.name as animal_label,
                an.species as animal_species
            FROM scheduling__appointments a
            LEFT JOIN identity_access__users u ON u.id = a.practitioner_user_id
            LEFT JOIN client__clients c ON c.id = a.owner_id
            LEFT JOIN animal__animals an ON an.id = a.animal_id
            WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
              AND a.starts_at_utc >= :fromUtc
              AND a.starts_at_utc <= :toUtc
        SQL;

        $params = [
            'clinicId' => $query->clinicId,
            'fromUtc'  => $query->fromUtc->format('Y-m-d H:i:s'),
            'toUtc'    => $query->toUtc->format('Y-m-d H:i:s'),
        ];

        if (null !== $query->practitionerUserId) {
            $sql .= ' AND a.practitioner_user_id = UUID_TO_BIN(:practitionerUserId)';
            $params['practitionerUserId'] = $query->practitionerUserId;
        }

        $sql .= ' ORDER BY a.starts_at_utc ASC';

        $results = $this->connection->fetchAllAssociative($sql, $params);

        $appointments = array_map(fn (array $row): AppointmentItem => $this->hydrateRow($row), $results);

        // Enrich appointments with isOrphaned
        $clinicId = ClinicId::fromString($query->clinicId);
        try {
            $clinicTz = $this->timezoneResolver->resolveTimezone($clinicId);
        } catch (\RuntimeException) {
            $clinicTz = new \DateTimeZone('UTC');
        }

        $blockRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT *,
                       LOWER(BIN_TO_UUID(id)) as id_str,
                       LOWER(BIN_TO_UUID(staff_user_id)) as staff_str
                FROM scheduling__planning_blocks
                WHERE clinic_id = :clinicId
                  AND date <= :toDate
                  AND (
                      JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) IS NULL
                      OR JSON_UNQUOTE(JSON_EXTRACT(recurrence_rule, '$.until')) >= :fromDate
                  )
            SQL,
            [
                'clinicId' => Uuid::fromString($query->clinicId)->toBinary(),
                'toDate'   => $query->toUtc->setTimezone($clinicTz)->format('Y-m-d'),
                'fromDate' => $query->fromUtc->setTimezone($clinicTz)->format('Y-m-d'),
            ],
        );

        $blockWindows = [];
        foreach ($blockRows as $row) {
            $rule        = RecurrenceRule::fromJson(RowAccessor::string($row, 'recurrence_rule'));
            $rangeStart  = $query->fromUtc->setTimezone($clinicTz);
            $rangeEnd    = $query->toUtc->setTimezone($clinicTz);
            $occurrences = $this->expander->expand($rule, RowAccessor::string($row, 'date'), $rangeStart, $rangeEnd);
            $type        = PlanningBlockType::from(RowAccessor::string($row, 'type'));
            $startTime   = RowAccessor::string($row, 'start_time');
            $endTime     = RowAccessor::string($row, 'end_time');
            $staffStr    = RowAccessor::string($row, 'staff_str');

            foreach ($occurrences as $occDate) {
                $blockUtcStart = (new \DateTimeImmutable($occDate . ' ' . $startTime, $clinicTz))
                    ->setTimezone(new \DateTimeZone('UTC'))
                ;
                $blockUtcEnd = (new \DateTimeImmutable($occDate . ' ' . $endTime, $clinicTz))
                    ->setTimezone(new \DateTimeZone('UTC'))
                ;
                $blockWindows[] = [
                    'vet'   => $staffStr,
                    'start' => $blockUtcStart,
                    'end'   => $blockUtcEnd,
                    'type'  => $type,
                ];
            }
        }

        $enriched = [];
        foreach ($appointments as $item) {
            $apptStart = new \DateTimeImmutable($item->startsAtUtc);
            $apptEnd   = $apptStart->modify('+' . $item->durationMinutes . ' minutes');

            $isOrphaned = true;
            foreach ($blockWindows as $w) {
                /** @var array{vet: string, start: \DateTimeImmutable, end: \DateTimeImmutable, type: PlanningBlockType} $w */
                $vetMatches  = $w['vet'] === $item->practitionerUserId;
                $typeMatches = $w['type']->acceptsAppointments();
                $inWindow    = $w['start'] <= $apptStart && $w['end'] >= $apptEnd;
                if ($vetMatches && $typeMatches && $inWindow) {
                    $isOrphaned = false;
                    break;
                }
            }

            $enriched[] = $item->withIsOrphaned($isOrphaned);
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): AppointmentItem
    {
        return new AppointmentItem(
            id: RowAccessor::string($row, 'id'),
            clinicId: RowAccessor::string($row, 'clinic_id'),
            linkedAdmissionId: RowAccessor::nullableString($row, 'linked_admission_id'),
            practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
            startsAtUtc: RowAccessor::string($row, 'starts_at_utc'),
            durationMinutes: RowAccessor::int($row, 'duration_minutes'),
            status: RowAccessor::string($row, 'status'),
            reason: RowAccessor::nullableString($row, 'reason'),
            notes: RowAccessor::nullableString($row, 'notes'),
            practitionerLabel: RowAccessor::nullableString($row, 'practitioner_label'),
            ownerId: RowAccessor::nullableString($row, 'owner_id'),
            ownerLabel: RowAccessor::nullableString($row, 'owner_label'),
            animalId: RowAccessor::nullableString($row, 'animal_id'),
            animalLabel: RowAccessor::nullableString($row, 'animal_label'),
            animalSpecies: RowAccessor::nullableString($row, 'animal_species'),
        );
    }
}
