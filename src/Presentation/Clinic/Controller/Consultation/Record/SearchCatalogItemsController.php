<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
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
            static fn (CatalogItemDto $item): bool => 'ACTIVE' === $item->status
                && ('' === $kind || $item->itemType === $kind),
        ));

        $items     = \array_slice($items, 0, self::MAX_RESULTS);
        $allergies = $this->allergyLabels($id, $clinicId);

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
                    'allergyConflicts'     => $this->allergyConflicts($item, $allergies, $clinicId),
                ],
                $items,
            ),
        ]);
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
