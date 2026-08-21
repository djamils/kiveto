<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Context\Animal\Application\Query\ListAnimalIdsMatching\ListAnimalIdsMatching;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route(path: '/consultations', name: 'clinic_consultations', methods: ['GET'])]
final class ListConsultationsController extends AbstractController
{
    /**
     * Page sizes offered by the footer selector.
     */
    private const array PAGE_SIZES = [10, 25, 50, 100];

    private const int DEFAULT_PAGE_SIZE = 25;

    /**
     * Species the clinic can record, in the order the filter menu shows them.
     */
    private const array SPECIES = ['dog', 'cat', 'nac', 'other'];

    private const string PERIOD_TODAY = 'today';
    private const string PERIOD_WEEK  = 'week';
    private const string PERIOD_MONTH = 'month';

    /**
     * Days of history each period keeps, counted back from today's midnight.
     */
    private const array PERIOD_DAYS = [
        self::PERIOD_TODAY => 0,
        self::PERIOD_WEEK  => 7,
        self::PERIOD_MONTH => 30,
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

        $search   = trim((string) $request->query->get('q', ''));
        $period   = $this->enumParam($request, 'period', array_keys(self::PERIOD_DAYS));
        $statuses = $this->listParam($request, 'status', ['OPEN', 'CLOSED']);
        $species  = $this->listParam($request, 'species', self::SPECIES);
        $sort     = $this->enumParam($request, 'sort', SearchConsultationsCriteria::sortableColumns())
            ?? SearchConsultationsCriteria::SORT_DATETIME;
        $direction = $this->enumParam($request, 'dir', ['asc', 'desc'])
            ?? (SearchConsultationsCriteria::SORT_STATUS === $sort ? 'asc' : 'desc');

        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = $request->query->getInt('per_page', self::DEFAULT_PAGE_SIZE);
        $pageSize = \in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE;

        $practitioners = $this->practitioners($clinicId);
        $vets          = $this->listParam($request, 'vet', array_map(
            static fn (ClinicVeterinarianItem $item): string => $item->userId,
            $practitioners,
        ));

        // Patient, owner and species are Animal data: resolve them to animal ids
        // before handing the filter to the Consultation context.
        $result = $this->queryBus->ask(new SearchConsultations(
            clinicId: $clinicId,
            searchTerm: '' !== $search ? $search : null,
            textMatchAnimalIds: '' !== $search ? $this->animalIds($clinicId, $search, null) : [],
            restrictToAnimalIds: [] === $species ? null : $this->animalIdsForSpecies($clinicId, $species),
            startedAfterUtc: $this->periodStart($period),
            statuses: $statuses,
            practitionerUserIds: $vets,
            practitionerOrder: array_map(
                static fn (ClinicVeterinarianItem $item): string => $item->userId,
                $practitioners,
            ),
            sort: $sort,
            direction: $direction,
            page: $page,
            limit: $pageSize,
        ));

        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));
        \assert(isset($result['total']) && \is_int($result['total']));

        /** @var list<ConsultationListItemView> $items */
        $items = array_values($result['items']);

        $pagination = $this->buildPagedView($result['total'], $page, $pageSize);

        // A hand-edited page number past the end would show an empty table under
        // a footer claiming otherwise: send the visitor to the last real page.
        if ($page > $pagination['totalPages']) {
            return $this->redirectToRoute(
                'clinic_consultations',
                ['page' => $pagination['totalPages']] + $request->query->all(),
            );
        }

        return $this->render('clinic/consultation/index.html.twig', [
            'consultations'  => $items,
            'patients'       => $this->enrichPatients($items),
            'practitioners'  => $practitioners,
            'pagination'     => $pagination,
            'pageSizes'      => self::PAGE_SIZES,
            'speciesOptions' => self::SPECIES,
            'periods'        => array_keys(self::PERIOD_DAYS),
            'filters'        => [
                'q'       => $search,
                'period'  => $period,
                'status'  => $statuses,
                'vet'     => $vets,
                'species' => $species,
            ],
            'sort'      => $sort,
            'direction' => $direction,
        ]);
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
     * Repeated query parameters, keeping only the values we know about.
     *
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    private function listParam(Request $request, string $key, array $allowed): array
    {
        $raw = $request->query->all($key);

        $values = [];

        foreach ($raw as $value) {
            if (\is_string($value) && \in_array($value, $allowed, true) && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Start of the selected period, as an instant.
     *
     * Timezone: Europe/Paris as sensible default — per-clinic TZ wiring is a V2 TODO
     */
    private function periodStart(?string $period): ?\DateTimeImmutable
    {
        if (null === $period) {
            return null;
        }

        $clinicTz = new \DateTimeZone('Europe/Paris');
        $midnight = (new \DateTimeImmutable('now', $clinicTz))->setTime(0, 0);

        return $midnight
            ->modify(\sprintf('-%d days', self::PERIOD_DAYS[$period]))
            ->setTimezone(new \DateTimeZone('UTC'))
        ;
    }

    /**
     * @return list<ClinicVeterinarianItem>
     */
    private function practitioners(string $clinicId): array
    {
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($veterinarians));

        $items = [];

        foreach ($veterinarians as $veterinarian) {
            if ($veterinarian instanceof ClinicVeterinarianItem) {
                $items[] = $veterinarian;
            }
        }

        return $items;
    }

    /**
     * @param list<string> $species
     *
     * @return list<string>
     */
    private function animalIdsForSpecies(string $clinicId, array $species): array
    {
        $animalIds = [];

        foreach ($species as $value) {
            foreach ($this->animalIds($clinicId, null, $value) as $animalId) {
                $animalIds[] = $animalId;
            }
        }

        return array_values(array_unique($animalIds));
    }

    /**
     * @return list<string>
     */
    private function animalIds(string $clinicId, ?string $searchTerm, ?string $species): array
    {
        $ids = $this->queryBus->ask(new ListAnimalIdsMatching($clinicId, $searchTerm, $species));
        \assert(\is_array($ids));

        $animalIds = [];

        foreach ($ids as $id) {
            if (\is_string($id)) {
                $animalIds[] = $id;
            }
        }

        return $animalIds;
    }

    /**
     * Patient label, animal identity and owner name for the rows of one page.
     *
     * A single DBAL query whatever the page size — the same batching the Flux
     * du jour uses, rather than one query-bus round trip per row.
     *
     * @param list<ConsultationListItemView> $items
     *
     * @return array<string, array{
     *     label: string,
     *     species: ?string,
     *     breed: ?string,
     *     birthDate: ?string,
     *     ownerName: ?string
     * }>
     */
    private function enrichPatients(array $items): array
    {
        $patientIds = [];

        foreach ($items as $item) {
            $patientIds[$item->patientId] = Uuid::fromString($item->patientId)->toBinary();
        }

        if ([] === $patientIds) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT BIN_TO_UUID(p.id) AS patient_id, p.display_label_value,
                    se.species, se.breed_name, se.search_owner_name, a.birth_date
             FROM patient__patients p
             LEFT JOIN animal__search_entries se ON se.id = p.animal_link_id
             LEFT JOIN animal__animals a ON a.id = p.animal_link_id
             WHERE p.id IN (?)',
            [array_values($patientIds)],
            [ArrayParameterType::BINARY],
        );

        $patients = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['patient_id']));

            $patients[$row['patient_id']] = [
                'label'     => \is_string($row['display_label_value']) ? $row['display_label_value'] : '—',
                'species'   => \is_string($row['species']) ? $row['species'] : null,
                'breed'     => \is_string($row['breed_name']) ? $row['breed_name'] : null,
                'birthDate' => \is_string($row['birth_date']) ? $row['birth_date'] : null,
                'ownerName' => \is_string($row['search_owner_name']) ? $row['search_owner_name'] : null,
            ];
        }

        return $patients;
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
