<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Animal\Application\Query\ListAnimalIdsMatching\ListAnimalIdsMatching;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Client\Application\Query\GetClientNamesByIds\GetClientNamesByIds;
use App\Context\Client\Application\Query\ListClientCities\ListClientCities;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Presentation\Clinic\Form\Animal\AnimalFormType;
use App\Presentation\Clinic\Form\Client\ClientFormType;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Répertoire — the clinic's clients and their animals in one table, two modes.
 *
 * Filtering, sorting and pagination are server-side and carried by the query
 * string, so a view is a shareable URL.
 */
#[Route('/clients', name: 'clinic_clients_list', methods: ['GET'])]
final class ListClientsController extends AbstractController
{
    private const string MODE_CLIENTS = 'clients';
    private const string MODE_ANIMALS = 'animals';

    private const array PAGE_SIZES = [10, 25, 50, 100];

    private const int DEFAULT_PAGE_SIZE = 25;

    /**
     * Animal chips shown on a client row before the "+N" overflow marker.
     */
    private const int ANIMAL_CHIPS_PER_CLIENT = 5;

    private const array CLIENT_STATUSES = ['active', 'archived'];

    private const array ANIMAL_LIFE_STATUSES = ['alive', 'deceased', 'missing'];

    private const array SPECIES = ['dog', 'cat', 'nac', 'other'];

    /**
     * Avatar palette. A client keeps the same colour wherever the row appears,
     * because the colour is derived from the identifier rather than the
     * position on the page.
     */
    private const array AVATAR_COLORS = [
        '#4338ca', '#0891b2', '#ea580c', '#16a34a', '#dc2626',
        '#7c3aed', '#0284c7', '#c2410c', '#059669', '#b91c1c',
    ];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        // "tab" is what the previous directory used; keep it working so old
        // links and the animals redirect still land on the right mode.
        $modeParam = (string) $request->query->get('mode', (string) $request->query->get('tab', self::MODE_CLIENTS));
        $mode      = \in_array($modeParam, [self::MODE_CLIENTS, self::MODE_ANIMALS], true)
            ? $modeParam
            : self::MODE_CLIENTS;

        $search = trim((string) $request->query->get('q', (string) $request->query->get('search', '')));
        $term   = '' !== $search ? $search : null;

        $cities = $this->cities($clinicId);

        $statuses     = $this->listParam($request, 'status', self::CLIENT_STATUSES);
        $cityFilter   = $this->listParam($request, 'city', $cities);
        $speciesList  = $this->listParam($request, 'species', self::SPECIES);
        $lifeStatuses = $this->listParam($request, 'life', self::ANIMAL_LIFE_STATUSES);

        $sortable = self::MODE_CLIENTS === $mode
            ? SearchClientsCriteria::sortableColumns()
            : SearchAnimalsCriteria::sortableColumns();

        $sort      = $this->enumParam($request, 'sort', $sortable) ?? 'name';
        $direction = $this->enumParam($request, 'dir', ['asc', 'desc']) ?? 'asc';

        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = $request->query->getInt('per_page', self::DEFAULT_PAGE_SIZE);
        $pageSize = \in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE;

        $clients      = [];
        $animals      = [];
        $enrichment   = [];
        $ownerNames   = [];
        $summaries    = [];
        $clientsTotal = 0;
        $animalsTotal = 0;

        if (self::MODE_CLIENTS === $mode) {
            $result = $this->queryBus->ask(new SearchClients(
                clinicId: $clinicId,
                searchTerm: $term,
                page: $page,
                limit: $pageSize,
                statuses: $statuses,
                cities: $cityFilter,
                sort: $sort,
                direction: $direction,
            ));

            $clientsTotal = $this->totalOf($result);
            $clients      = $this->clientItems($result);
            $animalsTotal = $this->countAnimals($clinicId, $term);

            $clientIds  = array_map(static fn (ClientListItemView $c): string => $c->id, $clients);
            $summaries  = $this->animalSummaries($clinicId, $clientIds);
            $enrichment = $this->lastVisitPerClient($clientIds);
        } else {
            $result = $this->queryBus->ask(new SearchAnimals(
                clinicId: $clinicId,
                page: $page,
                limit: $pageSize,
                speciesList: $speciesList,
                lifeStatuses: $lifeStatuses,
                // Free text spans the owner name, which lives in the Client
                // context: the Animal context resolves it to identifiers first.
                restrictToIds: null === $term ? null : $this->animalIdsMatching($clinicId, $term),
                sort: $sort,
                direction: $direction,
            ));

            $animalsTotal = $this->totalOf($result);
            $animals      = $this->animalItems($result);
            $clientsTotal = $this->countClients($clinicId, $term);

            $ownerNames = $this->ownerNames($clinicId, $animals);
            $enrichment = $this->alertsPerAnimal($animals);
        }

        $total      = self::MODE_CLIENTS === $mode ? $clientsTotal : $animalsTotal;
        $pagination = $this->buildPagedView($total, $page, $pageSize);

        if ($page > $pagination['totalPages']) {
            return $this->redirectToRoute(
                'clinic_clients_list',
                ['page' => $pagination['totalPages']] + $request->query->all(),
            );
        }

        return $this->render('clinic/clients/list/index.html.twig', [
            'mode'              => $mode,
            'clients'           => $clients,
            'animals'           => $animals,
            'clientCount'       => $clientsTotal,
            'animalCount'       => $animalsTotal,
            'animalSummaries'   => $summaries,
            'avatarColors'      => $this->avatarColors($clients),
            'ownerNames'        => $ownerNames,
            'enrichment'        => $enrichment,
            'cityOptions'       => $cities,
            'statusOptions'     => self::CLIENT_STATUSES,
            'speciesOptions'    => self::SPECIES,
            'lifeStatusOptions' => self::ANIMAL_LIFE_STATUSES,
            'pagination'        => $pagination,
            'pageSizes'         => self::PAGE_SIZES,
            'filters'           => [
                'q'       => $search,
                'status'  => $statuses,
                'city'    => $cityFilter,
                'species' => $speciesList,
                'life'    => $lifeStatuses,
            ],
            'sort'             => $sort,
            'direction'        => $direction,
            'createClientForm' => $this->createForm(ClientFormType::class),
            'createAnimalForm' => $this->createForm(AnimalFormType::class),
        ]);
    }

    private function totalOf(mixed $result): int
    {
        \assert(\is_array($result));
        \assert(isset($result['total']) && \is_int($result['total']));

        return $result['total'];
    }

    /**
     * @return list<ClientListItemView>
     */
    private function clientItems(mixed $result): array
    {
        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));

        $items = [];

        foreach ($result['items'] as $item) {
            if ($item instanceof ClientListItemView) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<AnimalListItemView>
     */
    private function animalItems(mixed $result): array
    {
        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));

        $items = [];

        foreach ($result['items'] as $item) {
            if ($item instanceof AnimalListItemView) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param list<string> $allowed
     */
    private function enumParam(Request $request, string $key, array $allowed): ?string
    {
        $value = (string) $request->query->get($key, '');

        return \in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    private function listParam(Request $request, string $key, array $allowed): array
    {
        $values = [];

        foreach ($request->query->all($key) as $value) {
            if (\is_string($value) && \in_array($value, $allowed, true) && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function cities(string $clinicId): array
    {
        $cities = $this->queryBus->ask(new ListClientCities($clinicId));
        \assert(\is_array($cities));

        $values = [];

        foreach ($cities as $city) {
            if (\is_string($city)) {
                $values[] = $city;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function animalIdsMatching(string $clinicId, string $term): array
    {
        $ids = $this->queryBus->ask(new ListAnimalIdsMatching($clinicId, $term, null));
        \assert(\is_array($ids));

        $values = [];

        foreach ($ids as $id) {
            if (\is_string($id)) {
                $values[] = $id;
            }
        }

        return $values;
    }

    /**
     * Count of the other mode, for its badge in the toggle.
     */
    private function countClients(string $clinicId, ?string $term): int
    {
        $result = $this->queryBus->ask(new SearchClients(
            clinicId: $clinicId,
            searchTerm: $term,
            limit: 1,
        ));

        \assert(\is_array($result) && isset($result['total']) && \is_int($result['total']));

        return $result['total'];
    }

    private function countAnimals(string $clinicId, ?string $term): int
    {
        $result = $this->queryBus->ask(new SearchAnimals(
            clinicId: $clinicId,
            limit: 1,
            restrictToIds: null === $term ? null : $this->animalIdsMatching($clinicId, $term),
        ));

        \assert(\is_array($result) && isset($result['total']) && \is_int($result['total']));

        return $result['total'];
    }

    /**
     * @param list<string> $clientIds
     *
     * @return array<array-key, mixed>
     */
    private function animalSummaries(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $summaries = $this->queryBus->ask(new ListAnimalSummariesPerClientIds(
            clinicId: $clinicId,
            clientIds: $clientIds,
            limit: self::ANIMAL_CHIPS_PER_CLIENT,
        ));

        return \is_array($summaries) ? $summaries : [];
    }

    /**
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, string>
     */
    private function ownerNames(string $clinicId, array $animals): array
    {
        $ownerIds = [];

        foreach ($animals as $animal) {
            if (null !== $animal->primaryOwnerClientId && !\in_array($animal->primaryOwnerClientId, $ownerIds, true)) {
                $ownerIds[] = $animal->primaryOwnerClientId;
            }
        }

        if ([] === $ownerIds) {
            return [];
        }

        $names = $this->queryBus->ask(new GetClientNamesByIds(clinicId: $clinicId, clientIds: $ownerIds));

        if (!\is_array($names)) {
            return [];
        }

        $result = [];

        foreach ($names as $id => $name) {
            if (\is_string($id) && \is_string($name)) {
                $result[$id] = $name;
            }
        }

        return $result;
    }

    /**
     * @param list<ClientListItemView> $clients
     *
     * @return array<string, string>
     */
    private function avatarColors(array $clients): array
    {
        $colors = [];

        foreach ($clients as $client) {
            $sum = 0;

            foreach (str_split(str_replace('-', '', $client->id)) as $character) {
                $sum += \ord($character);
            }

            $colors[$client->id] = self::AVATAR_COLORS[$sum % \count(self::AVATAR_COLORS)];
        }

        return $colors;
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

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT BIN_TO_UUID(o.client_id) AS client_id, a.name AS animal_name, c.started_at_utc
             FROM animal__ownerships o
             INNER JOIN animal__animals a ON a.id = o.animal_id
             INNER JOIN patient__patients p ON p.animal_link_id = a.id
             INNER JOIN consultation__consultations c ON c.patient_id = p.id
             WHERE o.client_id IN (?) AND o.status = 'active'
             ORDER BY c.started_at_utc DESC",
            [array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $clientIds)],
            [ArrayParameterType::BINARY],
        );

        $lastVisits = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['client_id']));
            \assert(\is_string($row['animal_name']));
            \assert(\is_string($row['started_at_utc']));

            // The rows come newest first, so the first one per client wins.
            $lastVisits[$row['client_id']] ??= [
                'startedAt'  => $row['started_at_utc'],
                'animalName' => $row['animal_name'],
            ];
        }

        return $lastVisits;
    }

    /**
     * Medical alerts of the listed animals, in one query.
     *
     * @param list<AnimalListItemView> $animals
     *
     * @return array<string, list<array{kind: string, label: string}>>
     */
    private function alertsPerAnimal(array $animals): array
    {
        if ([] === $animals) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT BIN_TO_UUID(animal_id) AS animal_id, kind, label
             FROM animal__medical_alerts
             WHERE animal_id IN (?)
             ORDER BY kind ASC, label ASC',
            [array_map(
                static fn (AnimalListItemView $animal): string => Uuid::fromString($animal->id)->toBinary(),
                $animals,
            )],
            [ArrayParameterType::BINARY],
        );

        $alerts = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['animal_id']));
            \assert(\is_string($row['kind']));
            \assert(\is_string($row['label']));

            $alerts[$row['animal_id']][] = ['kind' => $row['kind'], 'label' => $row['label']];
        }

        return $alerts;
    }

    /**
     * @return array{
     *     totalItems: int,
     *     currentPage: int,
     *     totalPages: int,
     *     limit: int,
     *     firstItem: int,
     *     lastItem: int,
     *     hasPreviousPage: bool,
     *     hasNextPage: bool,
     *     previousPage: int,
     *     nextPage: int,
     * }
     */
    private function buildPagedView(int $total, int $page, int $limit): array
    {
        $totalPages = max(1, (int) ceil($total / $limit));
        $page       = min($page, $totalPages);

        return [
            'totalItems'      => $total,
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'limit'           => $limit,
            'firstItem'       => 0 === $total ? 0 : ($page - 1) * $limit + 1,
            'lastItem'        => min($page * $limit, $total),
            'hasPreviousPage' => $page > 1,
            'hasNextPage'     => $page < $totalPages,
            'previousPage'    => max(1, $page - 1),
            'nextPage'        => min($totalPages, $page + 1),
        ];
    }
}
