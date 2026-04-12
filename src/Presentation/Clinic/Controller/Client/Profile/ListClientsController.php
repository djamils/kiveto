<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Context\Client\Application\Query\CountClients\CountClients;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\Form\Animal\AnimalFormType;
use App\Presentation\Clinic\Form\Client\ClientFormType;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients', name: 'clinic_clients_list', methods: ['GET'])]
final class ListClientsController extends AbstractController
{
    private const string TAB_CLIENTS = 'clients';
    private const string TAB_ANIMALS = 'animals';
    private const int PAGE_LIMIT     = 20;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $tabParam = (string) $request->query->get('tab', self::TAB_CLIENTS);
        $tab      = \in_array($tabParam, [self::TAB_CLIENTS, self::TAB_ANIMALS], true)
            ? $tabParam
            : self::TAB_CLIENTS;

        $search = trim((string) $request->query->get('search', ''));
        $page   = max(1, (int) $request->query->get('page', 1));

        $searchTerm = '' !== $search ? $search : null;

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clientsView = null;
        $animalsView = null;
        $clientCount = 0;
        $animalCount = 0;

        if (self::TAB_CLIENTS === $tab) {
            $result = $this->queryBus->ask(new SearchClients(
                clinicId: $currentClinicId->toString(),
                searchTerm: $searchTerm,
                page: $page,
                limit: self::PAGE_LIMIT,
            ));

            $clientsView = $this->buildPagedView($this->normalizeSearchResult($result), $page);
            $clientCount = $clientsView['totalItems'];

            $rawAnimalCount = $this->queryBus->ask(new CountAnimals(
                clinicId: $currentClinicId->toString(),
                searchTerm: $searchTerm,
            ));
            $animalCount = \is_int($rawAnimalCount) ? $rawAnimalCount : 0;
        } else {
            $result = $this->queryBus->ask(new SearchAnimals(
                clinicId: $currentClinicId->toString(),
                searchTerm: $searchTerm,
                page: $page,
                limit: self::PAGE_LIMIT,
            ));

            $animalsView = $this->buildPagedView($this->normalizeSearchResult($result), $page);
            $animalCount = $animalsView['totalItems'];

            $rawClientCount = $this->queryBus->ask(new CountClients(
                clinicId: $currentClinicId->toString(),
                searchTerm: $searchTerm,
            ));
            $clientCount = \is_int($rawClientCount) ? $rawClientCount : 0;
        }

        $createClientForm = $this->createForm(ClientFormType::class);
        $createAnimalForm = $this->createForm(AnimalFormType::class);

        return $this->render('clinic/clients/list/index.html.twig', [
            'activeTab'         => $tab,
            'search'            => $search,
            'clients'           => $clientsView,
            'animals'           => $animalsView,
            'clientCount'       => $clientCount,
            'animalCount'       => $animalCount,
            'createClientForm'  => $createClientForm,
            'createAnimalForm'  => $createAnimalForm,
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }

    /**
     * @return array{items: list<mixed>, total: int}
     */
    private function normalizeSearchResult(mixed $result): array
    {
        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));
        \assert(isset($result['total']) && \is_int($result['total']));

        return [
            'items' => array_values($result['items']),
            'total' => $result['total'],
        ];
    }

    /**
     * @param array{items: list<mixed>, total: int} $result
     *
     * @return array{
     *     items: list<mixed>,
     *     totalItems: int,
     *     currentPage: int,
     *     totalPages: int,
     *     limit: int,
     *     hasPreviousPage: bool,
     *     hasNextPage: bool,
     *     previousPage: int,
     *     nextPage: int,
     * }
     */
    private function buildPagedView(array $result, int $page): array
    {
        $totalPages = max(1, (int) ceil($result['total'] / self::PAGE_LIMIT));

        return [
            'items'           => $result['items'],
            'totalItems'      => $result['total'],
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'limit'           => self::PAGE_LIMIT,
            'hasPreviousPage' => $page > 1,
            'hasNextPage'     => $page < $totalPages,
            'previousPage'    => max(1, $page - 1),
            'nextPage'        => min($totalPages, $page + 1),
        ];
    }
}
