<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\AnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\ListAnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Application\Query\ListDirectoryMatches\ListDirectoryMatches;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Context\Billing\Application\Query\ListOutstandingBalances\ListOutstandingBalances;
use App\Context\Client\Application\Query\CountClients\CountClients;
use App\Context\Client\Application\Query\GetClientNamesByIds\GetClientNamesByIds;
use App\Context\Client\Application\Query\ListClientCities\ListClientCities;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Répertoire — the clinic's clients and animals in one table, two views
 * (docs/frontend-theme/kiveto-theme/repertoire-v-topbar_13.html).
 *
 * The facets that live in another context (an animal's species, alerts and
 * reminders, a client's unpaid balance) are resolved to identifiers first,
 * then narrow the paginated client or animal query.
 */
final readonly class DirectoryViewBuilder
{
    public const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇', 'other' => '🐾'];
    /**
     * Animals listed on a client row before the "+N" marker.
     */
    private const int ANIMALS_PER_CLIENT = 3;

    /**
     * Ceiling of animals fetched per client, so "+N" counts them all.
     */
    private const int ANIMALS_FETCHED_PER_CLIENT = 50;

    /**
     * Avatar tints of the mock-up. The tint follows the name, so a client keeps
     * it wherever their avatar appears.
     */
    private const array AVATAR_COLORS = ['#4f86c6', '#6dbe78', '#c67db5', '#d98c4a', '#8b7cc8', '#4ab3af', '#c75f5f', '#b89c3f'];

    private const array SPECIES_LABEL = ['dog' => 'Chien', 'cat' => 'Chat', 'nac' => 'NAC', 'other' => 'Autre'];

    private const array GONE_LABEL = ['deceased' => 'Décédé', 'missing' => 'Disparu'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $clinicId, Request $request, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $cities  = $this->cities($clinicId);
        $filters = DirectoryFilters::fromRequest($request, $cities);

        [$rows, $total] = $filters->isClients()
            ? $this->clients($clinicId, $filters, $tz, $now)
            : $this->animals($clinicId, $filters, $tz, $now);

        return [
            'filters'     => $filters,
            'cityOptions' => $cities,
            'counts'      => [
                'clients' => $this->int($this->queryBus->ask(new CountClients($clinicId))),
                'animals' => $this->int($this->queryBus->ask(new CountAnimals($clinicId))),
            ],
            'rows'       => $rows,
            'pagination' => $this->pagination($total, $filters->page, $filters->pageSize),
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function clients(string $clinicId, DirectoryFilters $filters, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $balances = $this->balances($clinicId);
        $restrict = null;

        if ([] !== $filters->species) {
            $restrict = $this->matches($clinicId, null, $filters->species)->ownerIds();
        }

        if ([] !== $filters->states) {
            $flagged = [];

            if ($filters->has(DirectoryFilters::STATE_ALERT) || $filters->has(DirectoryFilters::STATE_REMINDER)) {
                $flagged = $this->matches(
                    $clinicId,
                    null,
                    [],
                    $filters->has(DirectoryFilters::STATE_ALERT),
                    $filters->has(DirectoryFilters::STATE_REMINDER),
                )->ownerIds();
            }

            if ($filters->has(DirectoryFilters::STATE_UNPAID)) {
                $flagged = [...$flagged, ...array_map('strval', array_keys($balances))];
            }

            $restrict = null === $restrict ? array_values(array_unique($flagged)) : array_values(array_intersect($restrict, $flagged));
        }

        $result = $this->queryBus->ask(new SearchClients(
            clinicId: $clinicId,
            searchTerm: '' === $filters->search ? null : $filters->search,
            page: $filters->page,
            limit: $filters->pageSize,
            statuses: $filters->statuses,
            cities: $filters->cities,
            direction: $filters->direction,
            restrictToIds: $restrict,
            // The box also finds a client by their animal's name or chip number.
            termMatchingIds: '' === $filters->search ? [] : $this->matches($clinicId, $filters->search)->ownerIds(),
        ));
        \assert(\is_array($result) && isset($result['items'], $result['total']) && \is_array($result['items']) && \is_int($result['total']));

        $clients = array_values(array_filter($result['items'], static fn (mixed $c): bool => $c instanceof ClientListItemView));
        $ids     = array_map(static fn (ClientListItemView $c): string => $c->id, $clients);
        $animals = $this->animalsOf($clinicId, $ids);
        $visits  = $this->lastVisitPerClient($ids);

        $rows = [];

        foreach ($clients as $client) {
            $owned = $animals[$client->id] ?? [];
            $visit = $visits[$client->id] ?? null;
            $due   = $balances[$client->id] ?? 0;

            $rows[] = [
                'id'       => $client->id,
                'name'     => $client->fullName(),
                'initials' => mb_strtoupper(mb_substr($client->firstName, 0, 1) . mb_substr($client->lastName, 0, 1)),
                'color'    => $this->avatarColor($client->fullName()),
                'city'     => $client->city,
                'phone'    => $client->primaryPhone,
                'email'    => $client->primaryEmail,
                'animals'  => array_map(
                    static fn (AnimalSummary $a): array => ['name' => $a->name, 'emoji' => self::SPECIES_EMOJI[$a->species] ?? '🐾'],
                    \array_slice($owned, 0, self::ANIMALS_PER_CLIENT),
                ),
                'more'  => max(0, \count($owned) - self::ANIMALS_PER_CLIENT),
                'visit' => null === $visit ? null : [
                    'label'  => $this->ago(new \DateTimeImmutable($visit['startedAt'], new \DateTimeZone('UTC')), $tz, $now),
                    'animal' => $visit['animalName'],
                ],
                'balance'  => $due > 0 ? SheetText::money($due, 'EUR') : null,
                'archived' => 'active' !== $client->status,
                'since'    => SheetText::sentence(SheetText::date((new \DateTimeImmutable($client->createdAt))->setTimezone($tz), 'MMM y')),
                // What the agenda's patient search starts on for "Nouveau rendez-vous".
                'pick' => 1 === \count($owned) ? $owned[0]->name : $client->lastName,
            ];
        }

        return [$rows, $result['total']];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function animals(string $clinicId, DirectoryFilters $filters, \DateTimeZone $tz, \DateTimeImmutable $now): array
    {
        $restrict = null;
        $term     = '' === $filters->search ? null : $filters->search;

        if (null !== $term || [] !== $filters->species || [] !== $filters->states) {
            $alert    = $filters->has(DirectoryFilters::STATE_ALERT);
            $reminder = $filters->has(DirectoryFilters::STATE_REMINDER);
            $unpaid   = $filters->has(DirectoryFilters::STATE_UNPAID);

            // The states are alternatives: flagged by the Animal context, or
            // belonging to an owner the Billing context says still owes.
            $restrict = [];

            if ($alert || $reminder || !$unpaid) {
                $restrict = $this->matches($clinicId, $term, $filters->species, $alert, $reminder)->animalIds();
            }

            if ($unpaid) {
                $debtors = $this->balances($clinicId);

                foreach ($this->matches($clinicId, $term, $filters->species)->owners as $animalId => $ownerId) {
                    if (null !== $ownerId && isset($debtors[$ownerId])) {
                        $restrict[] = (string) $animalId;
                    }
                }

                $restrict = array_values(array_unique($restrict));
            }
        }

        $result = $this->queryBus->ask(new SearchAnimals(
            clinicId: $clinicId,
            page: $filters->page,
            limit: $filters->pageSize,
            restrictToIds: $restrict,
            direction: $filters->direction,
        ));
        \assert(\is_array($result) && isset($result['items'], $result['total']) && \is_array($result['items']) && \is_int($result['total']));

        $animals = array_values(array_filter($result['items'], static fn (mixed $a): bool => $a instanceof AnimalListItemView));
        $ids     = array_map(static fn (AnimalListItemView $a): string => $a->id, $animals);
        $owners  = $this->ownerNames($clinicId, $animals);
        $visits  = $this->lastVisitPerAnimal($ids);
        $facts   = $this->facts($clinicId, $ids, $now->setTimezone($tz));
        $today   = new \DateTimeImmutable($now->setTimezone($tz)->format('Y-m-d'));

        $rows = [];

        foreach ($animals as $animal) {
            $fact  = $facts[$animal->id] ?? new AnimalDirectoryFacts([], []);
            $breed = $animal->breedName ?? self::SPECIES_LABEL[$animal->species] ?? $animal->species;
            $age   = $animal->ageLabel($now);

            $rows[] = [
                'id'        => $animal->id,
                'name'      => $animal->name,
                'emoji'     => self::SPECIES_EMOJI[$animal->species] ?? '🐾',
                'breedAge'  => null === $age ? $breed : $breed . ' · ' . $age,
                'ownerId'   => $animal->primaryOwnerClientId,
                'owner'     => null === $animal->primaryOwnerClientId ? null : $owners[$animal->primaryOwnerClientId] ?? null,
                'chip'      => $animal->microchipNumber,
                'reminders' => array_map(fn ($r): string => $r->label . ' ' . $this->dueIn($r->nextDueOn, $today), $fact->dueReminders),
                'alerts'    => array_values(array_map(
                    static fn ($a): string => '⚠ ' . $a->label,
                    array_filter($fact->alerts, static fn ($a): bool => 'ALLERGY' === $a->kind),
                )),
                'follow' => array_values(array_map(
                    static fn ($a): string => $a->label,
                    array_filter($fact->alerts, static fn ($a): bool => 'ALLERGY' !== $a->kind),
                )),
                'visit' => isset($visits[$animal->id])
                    ? $this->ago(new \DateTimeImmutable($visits[$animal->id], new \DateTimeZone('UTC')), $tz, $now)
                    : null,
                'badge' => 'active' !== $animal->status ? 'Archivé' : self::GONE_LABEL[$animal->lifeStatus] ?? null,
            ];
        }

        return [$rows, $result['total']];
    }

    /**
     * @param list<string> $species
     */
    private function matches(
        string $clinicId,
        ?string $term,
        array $species = [],
        bool $alert = false,
        bool $reminder = false,
    ): DirectoryMatches {
        $matches = $this->queryBus->ask(new ListDirectoryMatches($clinicId, $term, $species, $alert, $reminder));
        \assert($matches instanceof DirectoryMatches);

        return $matches;
    }

    /**
     * @param list<string> $animalIds
     *
     * @return array<string, AnimalDirectoryFacts>
     */
    private function facts(string $clinicId, array $animalIds, \DateTimeImmutable $today): array
    {
        $facts = $this->queryBus->ask(new ListAnimalDirectoryFacts($clinicId, $animalIds, $today));
        \assert(\is_array($facts));

        $typed = [];

        foreach ($facts as $animalId => $fact) {
            if ($fact instanceof AnimalDirectoryFacts) {
                $typed[(string) $animalId] = $fact;
            }
        }

        return $typed;
    }

    /**
     * @return array<string, int>
     */
    private function balances(string $clinicId): array
    {
        $balances = $this->queryBus->ask(new ListOutstandingBalances($clinicId));
        \assert(\is_array($balances));

        $typed = [];

        foreach ($balances as $clientId => $due) {
            if (\is_int($due)) {
                $typed[(string) $clientId] = $due;
            }
        }

        return $typed;
    }

    /**
     * @return list<string>
     */
    private function cities(string $clinicId): array
    {
        $cities = $this->queryBus->ask(new ListClientCities($clinicId));
        \assert(\is_array($cities));

        return array_values(array_filter($cities, 'is_string'));
    }

    /**
     * @param list<string> $clientIds
     *
     * @return array<string, list<AnimalSummary>>
     */
    private function animalsOf(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $summaries = $this->queryBus->ask(new ListAnimalSummariesPerClientIds(
            clinicId: $clinicId,
            clientIds: $clientIds,
            limit: self::ANIMALS_FETCHED_PER_CLIENT,
        ));
        \assert(\is_array($summaries));

        $typed = [];

        foreach ($summaries as $clientId => $list) {
            \assert(\is_array($list));
            $typed[(string) $clientId] = array_values(array_filter($list, static fn (mixed $a): bool => $a instanceof AnimalSummary));
        }

        return $typed;
    }

    /**
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, string>
     */
    private function ownerNames(string $clinicId, array $animals): array
    {
        $ownerIds = array_values(array_unique(array_filter(array_map(
            static fn (AnimalListItemView $a): ?string => $a->primaryOwnerClientId,
            $animals,
        ))));

        if ([] === $ownerIds) {
            return [];
        }

        $names = $this->queryBus->ask(new GetClientNamesByIds(clinicId: $clinicId, clientIds: $ownerIds));
        \assert(\is_array($names));

        $typed = [];

        foreach ($names as $id => $name) {
            if (\is_string($name)) {
                $typed[(string) $id] = $name;
            }
        }

        return $typed;
    }

    /**
     * Latest consultation of each client's animals, in one query.
     *
     * The chain client → animal → patient → consultation crosses three
     * contexts; batching it here follows the Flux du jour, which enriches its
     * page the same way rather than per row.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, array{startedAt: string, animalName: string}>
     */
    private function lastVisitPerClient(array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(o.client_id) AS client_id, a.name AS animal_name, c.started_at_utc
             FROM animal__ownerships o
             INNER JOIN animal__animals a ON a.id = o.animal_id
             INNER JOIN patient__patients p ON p.animal_link_id = a.id
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE o.client_id IN (?) AND o.status = 'active'
             ORDER BY c.started_at_utc DESC",
            [$this->binary($clientIds)],
            [ArrayParameterType::BINARY],
        );

        $visits = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['client_id']) && \is_string($row['animal_name']) && \is_string($row['started_at_utc']));

            // The rows come newest first, so the first one per client wins.
            $visits[$row['client_id']] ??= ['startedAt' => $row['started_at_utc'], 'animalName' => $row['animal_name']];
        }

        return $visits;
    }

    /**
     * Latest consultation of each listed animal, in one query.
     *
     * @param list<string> $animalIds
     *
     * @return array<string, string>
     */
    private function lastVisitPerAnimal(array $animalIds): array
    {
        if ([] === $animalIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(p.animal_link_id) AS animal_id, MAX(c.started_at_utc) AS started_at_utc
             FROM patient__patients p
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE p.animal_link_id IN (?)
             GROUP BY p.animal_link_id',
            [$this->binary($animalIds)],
            [ArrayParameterType::BINARY],
        );

        $visits = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['animal_id']) && \is_string($row['started_at_utc']));
            $visits[$row['animal_id']] = $row['started_at_utc'];
        }

        return $visits;
    }

    /**
     * « Aujourd'hui », « Hier », « Il y a 12 j », « Il y a 14 mois » — the
     * wording of the mock-up, counted in calendar days of the clinic.
     */
    private function ago(\DateTimeImmutable $at, \DateTimeZone $tz, \DateTimeImmutable $now): string
    {
        $day   = new \DateTimeImmutable($at->setTimezone($tz)->format('Y-m-d'));
        $today = new \DateTimeImmutable($now->setTimezone($tz)->format('Y-m-d'));
        $days  = max(0, (int) $day->diff($today)->days);

        return match (true) {
            0 === $days => 'Aujourd\'hui',
            1 === $days => 'Hier',
            $days < 45  => 'Il y a ' . $days . ' j',
            $days < 730 => 'Il y a ' . intdiv($days, 30) . ' mois',
            default     => 'Il y a ' . intdiv($days, 365) . ' ans',
        };
    }

    /**
     * « dans 12 j », « aujourd'hui », « en retard de 5 j ».
     */
    private function dueIn(\DateTimeImmutable $dueOn, \DateTimeImmutable $today): string
    {
        $days = (int) $today->diff($dueOn)->format('%r%a');

        return match (true) {
            0 === $days => 'aujourd\'hui',
            $days > 0   => 'dans ' . $days . ' j',
            default     => 'en retard de ' . -$days . ' j',
        };
    }

    private function avatarColor(string $name): string
    {
        $sum = 0;

        foreach (mb_str_split($name) as $character) {
            $sum += mb_ord($character);
        }

        return self::AVATAR_COLORS[$sum % \count(self::AVATAR_COLORS)];
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function binary(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }

    private function int(mixed $value): int
    {
        \assert(\is_int($value));

        return $value;
    }

    /**
     * @return array{total: int, page: int, pages: int, size: int, shown: list<int>}
     */
    private function pagination(int $total, int $page, int $size): array
    {
        $pages = max(1, (int) ceil($total / $size));
        $page  = min($page, $pages);

        // First, last and the current page's neighbours; gaps become « … ».
        $shown = array_values(array_unique(array_filter(
            [1, $page - 1, $page, $page + 1, $pages],
            static fn (int $p): bool => $p >= 1 && $p <= $pages,
        )));
        sort($shown);

        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'size' => $size, 'shown' => $shown];
    }
}
