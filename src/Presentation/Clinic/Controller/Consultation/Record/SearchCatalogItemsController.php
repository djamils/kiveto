<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\ListStockItemsByClinic;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitStateBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalog lookup behind the cockpit's medication and act pickers.
 *
 * An empty term returns the head of the catalogue: both pickers load one page
 * on opening and filter it client-side, so the list feels instant.
 */
final class SearchCatalogItemsController extends AbstractController
{
    private const int MAX_RESULTS = 40;

    /**
     * The Catalog search does not filter on status, so it is asked for more rows
     * than needed; archived items are dropped before the cap is applied.
     */
    private const int SEARCH_FETCH_LIMIT = 80;

    public function __construct(
        private readonly CatalogItemProviderInterface $catalogItems,
        private readonly CockpitStateBuilder $stateBuilder,
        private readonly QueryBusInterface $queryBus,
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
        $clinicId = $this->endpoint->currentClinicId();
        $term     = trim($request->query->getString('term'));
        $kind     = $request->query->getString('kind');

        $items = array_values(array_filter(
            $this->catalogItems->search($term, $clinicId, self::SEARCH_FETCH_LIMIT),
            static fn (CatalogItemDto $item): bool => 'ACTIVE' === $item->status && self::matchesKind($item, $kind),
        ));

        $items     = \array_slice($items, 0, self::MAX_RESULTS);
        $allergies = $this->allergyLabels($id, $clinicId);
        $stock     = $this->stockByArticle($clinicId);

        return new JsonResponse([
            'success' => true,
            'items'   => array_map(
                fn (CatalogItemDto $item): array => [
                    'itemType'             => $item->itemType,
                    'itemId'               => $item->itemId,
                    'name'                 => $item->name,
                    'code'                 => $item->code,
                    'requiresPrescription' => $item->requiresPrescription,
                    'basePriceMinorUnits'  => $item->basePriceMinorUnits,
                    'currency'             => $item->currency,
                    'status'               => $item->status,
                    'articleKind'          => $item->articleKind,
                    'stock'                => 'ARTICLE' === $item->itemType ? ($stock[$item->itemId] ?? null) : null,
                    'allergyConflicts'     => $this->allergyConflicts($item, $allergies, $clinicId),
                ],
                $items,
            ),
        ]);
    }

    /**
     * `kind` narrows the list to one picker of the plan editor: ACT, MED (drug
     * articles), PRD (every other article) or ARTICLE.
     */
    private static function matchesKind(CatalogItemDto $item, string $kind): bool
    {
        return match ($kind) {
            ''      => true,
            'MED'   => 'ARTICLE' === $item->itemType && 'DRUG' === $item->articleKind,
            'PRD'   => 'ARTICLE' === $item->itemType && 'DRUG' !== $item->articleKind,
            default => $item->itemType === $kind,
        };
    }

    /**
     * Quantity available per stock-tracked article, so the picker can show
     * « Stock 4 » or « Rupture » next to each product.
     *
     * @return array<string, float>
     */
    private function stockByArticle(string $clinicId): array
    {
        $rows = $this->queryBus->ask(new ListStockItemsByClinic($clinicId, null, 1000));

        if (!\is_array($rows)) {
            return [];
        }

        $stock = [];

        foreach ($rows as $row) {
            if ($row instanceof StockItemSummaryView && $row->trackStock) {
                $stock[$row->articleId] = (float) $row->availableQuantityAmount;
            }
        }

        return $stock;
    }

    /**
     * @return list<string>
     */
    private function allergyLabels(string $consultationId, string $clinicId): array
    {
        $details = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));

        if (!$details instanceof ConsultationDetailsDTO) {
            return [];
        }

        return $this->stateBuilder->allergyLabelsFor($details->patientId, $clinicId);
    }

    /**
     * Allergy labels matching one of the article's active substances. Plain
     * label matching, like the prescription warning — not an interaction engine.
     *
     * @param list<string> $allergies
     *
     * @return list<string>
     */
    private function allergyConflicts(CatalogItemDto $item, array $allergies, string $clinicId): array
    {
        if ([] === $allergies || 'ARTICLE' !== $item->itemType) {
            return [];
        }

        $substances = $this->catalogItems->activeSubstances($item->itemId, $clinicId);

        if ([] === $substances) {
            return [];
        }

        $conflicts = [];

        foreach ($allergies as $allergy) {
            foreach ($substances as $substance) {
                if (str_contains(mb_strtolower($substance), mb_strtolower($allergy))) {
                    $conflicts[] = $allergy;

                    break;
                }
            }
        }

        return $conflicts;
    }
}
