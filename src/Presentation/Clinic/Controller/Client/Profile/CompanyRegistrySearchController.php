<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Profile;

use App\Context\Client\Application\Query\SearchCompanyRegistry\CompanySearchResult;
use App\Context\Client\Application\Query\SearchCompanyRegistry\SearchCompanyRegistry;
use App\Presentation\Clinic\ViewModel\Client\CompanyHitPresenter;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Raison sociale, SIREN ou SIRET » in the tax-profile screens (counter,
 * directory window, client sheet), as JSON: {available, reason, items}. An
 * outage answers `available: false` with `throttled` or `down`, never an
 * empty list.
 */
#[Route('/clients/company-registry/search', name: 'clinic_clients_company_registry_search', methods: ['GET'])]
final class CompanyRegistrySearchController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CompanyHitPresenter $presenter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->queryBus->ask(new SearchCompanyRegistry($request->query->getString('q')));
        \assert($result instanceof CompanySearchResult);

        return new JsonResponse($this->presenter->result($result));
    }
}
