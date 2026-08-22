<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The lookup behind the pickers of the catalogue: what goes into a package, and
 * what a price list overrides.
 *
 * Archived items are dropped — a package cannot hold one anyway, and the domain
 * would refuse it.
 */
#[Route(path: '/catalog/search', name: 'clinic_catalogue_search', methods: ['GET'])]
final class SearchCatalogItemsController extends AbstractController
{
    private const int MAX_RESULTS = 25;

    /** The search does not filter on status, so it is asked for more than it needs. */
    private const int FETCH_LIMIT = 60;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $term = mb_trim($request->query->getString('term'));
        $type = $request->query->getString('type');

        $results = $this->queryBus->ask(new SearchCatalogItems(
            clinicId: $currentClinicId->toString(),
            term: $term,
            limit: self::FETCH_LIMIT,
        ));

        $items = [];

        foreach (\is_array($results) ? $results : [] as $result) {
            if (!$result instanceof CatalogSearchResult || 'ACTIVE' !== $result->status) {
                continue;
            }

            if ('' !== $type && $result->type !== $type) {
                continue;
            }

            $items[] = [
                'itemType'            => $result->type,
                'itemId'              => $result->id,
                'code'                => $result->code,
                'name'                => $result->name,
                'basePriceMinorUnits' => $result->basePriceMinorUnits,
                'currency'            => $result->basePriceCurrency,
            ];

            if (self::MAX_RESULTS === \count($items)) {
                break;
            }
        }

        return new JsonResponse(['success' => true, 'items' => $items]);
    }
}
