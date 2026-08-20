<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalog lookup behind the cockpit's medication and act search modals.
 */
final class SearchCatalogItemsController extends AbstractController
{
    private const int MAX_RESULTS = 30;

    /**
     * The Catalog search does not filter on status, so it is asked for more rows
     * than needed; archived items are dropped before the cap is applied.
     */
    private const int SEARCH_FETCH_LIMIT = 60;

    public function __construct(
        private readonly CatalogItemProviderInterface $catalogItems,
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route(
        '/clinic/consultations/{id}/catalog/search',
        name: 'clinic_consultation_catalog_search',
        methods: ['GET'],
    )]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $term = trim($request->query->getString('term'));
        $kind = $request->query->getString('kind');

        if ('' === $term) {
            return new JsonResponse(['success' => true, 'items' => []]);
        }

        $items = $this->catalogItems->search(
            $term,
            $this->endpoint->currentClinicId(),
            self::SEARCH_FETCH_LIMIT,
        );

        $items = array_values(array_filter(
            $items,
            static fn (CatalogItemDto $item): bool => 'ACTIVE' === $item->status
                && ('' === $kind || $item->itemType === $kind),
        ));

        return new JsonResponse([
            'success' => true,
            'items'   => array_map(
                static fn (CatalogItemDto $item): array => [
                    'itemType'             => $item->itemType,
                    'itemId'               => $item->itemId,
                    'name'                 => $item->name,
                    'code'                 => $item->code,
                    'requiresPrescription' => $item->requiresPrescription,
                    'basePriceMinorUnits'  => $item->basePriceMinorUnits,
                    'currency'             => $item->currency,
                    'status'               => $item->status,
                ],
                \array_slice($items, 0, self::MAX_RESULTS),
            ),
        ]);
    }
}
