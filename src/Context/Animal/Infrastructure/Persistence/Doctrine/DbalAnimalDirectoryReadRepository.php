<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\AnimalDirectoryReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\AnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\DirectoryAlertView;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\DirectoryReminderView;
use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAnimalDirectoryReadRepository implements AnimalDirectoryReadRepositoryInterface
{
    /**
     * The latest act of each protocol of an animal — an older act of the same
     * protocol never makes a reminder due once it has been renewed.
     */
    private const string LATEST_ACTS = '
        SELECT animal_id, label, next_due_on
        FROM (
            SELECT animal_id, label, next_due_on,
                   ROW_NUMBER() OVER (
                       PARTITION BY animal_id, kind, LOWER(label)
                       ORDER BY administered_on DESC, recorded_at DESC
                   ) AS rank_in_protocol
            FROM animal__preventive_care_records
            WHERE clinic_id = :clinicId
        ) acts
        WHERE rank_in_protocol = 1 AND next_due_on IS NOT NULL AND next_due_on <= :dueBy';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function matches(
        ClinicId $clinicId,
        ?string $searchTerm,
        array $species,
        bool $withMedicalAlert,
        bool $withDueReminder,
        \DateTimeImmutable $dueBy,
    ): DirectoryMatches {
        $where  = ['e.clinic_id = :clinicId'];
        $params = ['clinicId' => $this->binary($clinicId->toString())];
        $types  = [];

        if (null !== $searchTerm) {
            $text = 'e.search_name LIKE :term OR e.search_owner_name LIKE :term OR e.breed_name LIKE :term';

            // Chip numbers are stored as bare digits: compare digits with digits.
            $digits = (string) preg_replace('/\D+/', '', $searchTerm);
            if (\strlen($digits) >= 3) {
                $text .= ' OR e.search_chip LIKE :chip';
                $params['chip'] = '%' . $digits . '%';
            }

            $where[]        = '(' . $text . ')';
            $params['term'] = '%' . $searchTerm . '%';
        }

        if ([] !== $species) {
            $where[]           = 'e.species IN (:species)';
            $params['species'] = $species;
            $types['species']  = ArrayParameterType::STRING;
        }

        $flags = [];

        if ($withMedicalAlert) {
            $flags[] = 'EXISTS (SELECT 1 FROM animal__medical_alerts ma WHERE ma.animal_id = e.id)';
        }

        if ($withDueReminder) {
            $flags[]         = 'e.id IN (SELECT due.animal_id FROM (' . self::LATEST_ACTS . ') due)';
            $params['dueBy'] = $dueBy->format('Y-m-d');
        }

        if ([] !== $flags) {
            $where[] = '(' . implode(' OR ', $flags) . ')';
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT e.id, e.primary_owner_client_id FROM animal__search_entries e WHERE ' . implode(' AND ', $where),
            $params,
            $types,
        );

        $owners = [];

        foreach ($rows as $row) {
            $owners[RowAccessor::uuid($row, 'id')] = RowAccessor::nullableUuid($row, 'primary_owner_client_id');
        }

        return new DirectoryMatches($owners);
    }

    public function facts(ClinicId $clinicId, array $animalIds, \DateTimeImmutable $dueBy): array
    {
        if ([] === $animalIds) {
            return [];
        }

        $clinic = $this->binary($clinicId->toString());
        $ids    = array_map($this->binary(...), $animalIds);

        $alertRows = $this->connection->fetchAllAssociative(
            'SELECT ma.animal_id, ma.kind, ma.label
             FROM animal__medical_alerts ma
             INNER JOIN animal__animals a ON a.id = ma.animal_id
             WHERE a.clinic_id = :clinicId AND ma.animal_id IN (:ids)
             ORDER BY ma.kind ASC, ma.label ASC',
            ['clinicId' => $clinic, 'ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );

        $reminderRows = $this->connection->fetchAllAssociative(
            'SELECT due.animal_id, due.label, due.next_due_on FROM (' . self::LATEST_ACTS . ') due
             WHERE due.animal_id IN (:ids)
             ORDER BY due.next_due_on ASC, due.label ASC',
            ['clinicId' => $clinic, 'dueBy' => $dueBy->format('Y-m-d'), 'ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );

        $alerts    = [];
        $reminders = [];

        foreach ($alertRows as $row) {
            $alerts[RowAccessor::uuid($row, 'animal_id')][] = new DirectoryAlertView(
                kind: RowAccessor::string($row, 'kind'),
                label: RowAccessor::string($row, 'label'),
            );
        }

        foreach ($reminderRows as $row) {
            $reminders[RowAccessor::uuid($row, 'animal_id')][] = new DirectoryReminderView(
                label: RowAccessor::string($row, 'label'),
                nextDueOn: new \DateTimeImmutable(RowAccessor::string($row, 'next_due_on')),
            );
        }

        $facts = [];

        foreach (array_unique([...array_keys($alerts), ...array_keys($reminders)]) as $animalId) {
            $facts[$animalId] = new AnimalDirectoryFacts($alerts[$animalId] ?? [], $reminders[$animalId] ?? []);
        }

        return $facts;
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }
}
