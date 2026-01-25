<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal;

use App\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals', name: 'clinic_animals_list', methods: ['GET'])]
final class ListAnimalsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $search = trim((string) $request->query->get('search', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = 20;

        $result = $this->queryBus->ask(new SearchAnimals(
            clinicId: $currentClinicId->toString(),
            searchTerm: '' !== $search ? $search : null,
            page: $page,
            limit: $limit,
        ));

        \assert(\is_array($result));
        \assert(isset($result['items']));
        \assert(isset($result['total']));
        \assert(\is_array($result['items']));
        \assert(\is_int($result['total']));

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $totalPages = (int) ceil($result['total'] / $limit);

        return $this->render('clinic/animals/list_layout15.html.twig', [
            'animals' => [
                'items'           => array_values($result['items']),
                'totalItems'      => $result['total'],
                'currentPage'     => $page,
                'totalPages'      => $totalPages,
                'limit'           => $limit,
                'hasPreviousPage' => $page > 1,
                'hasNextPage'     => $page < $totalPages,
                'previousPage'    => max(1, $page - 1),
                'nextPage'        => min($totalPages, $page + 1),
            ],
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
