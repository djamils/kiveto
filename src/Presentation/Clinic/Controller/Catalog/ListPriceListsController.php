<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPriceListEntries;
use App\Context\Catalog\Application\Query\Pricing\GetPriceList\GetPriceList;
use App\Context\Catalog\Application\Query\Pricing\GetPriceList\PriceListView;
use App\Context\Catalog\Application\Query\Pricing\ListPriceLists\ListPriceLists;
use App\Context\Catalog\Application\Query\Pricing\ListPriceLists\PriceListCollection;
use App\Context\Catalog\Application\Query\Pricing\ListPriceLists\PriceListSummary;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The price lists of the clinic, and the overrides of whichever one is open.
 */
#[Route(path: '/catalogue/tarifs', name: 'clinic_catalogue_price_lists', methods: ['GET'])]
final class ListPriceListsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CatalogPageContext $context,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        $collection = $this->queryBus->ask(new ListPriceLists($clinicId));
        $priceLists = $collection instanceof PriceListCollection ? $collection->items : [];

        // The default list is the one the clinic charges from, so it opens first
        // unless another one was asked for.
        $selected = $this->selected($priceLists, mb_trim($request->query->getString('list')));

        $entries = [];
        $rules   = [];

        if (null !== $selected) {
            $rows    = $this->queryBus->ask(new ListPriceListEntries($clinicId, $selected->id));
            $entries = \is_array($rows) ? $rows : [];

            $detail = $this->queryBus->ask(new GetPriceList($selected->id, $clinicId));
            $rules  = $detail instanceof PriceListView ? $detail->rules : [];
        }

        return $this->render('clinic/catalog/price_lists.html.twig', [
            'priceLists'    => $priceLists,
            'selected'      => $selected,
            'entries'       => $entries,
            'rules'         => $rules,
            'counters'      => $this->context->counters($clinicId),
            'taxCategories' => $this->context->taxCategories(),
        ]);
    }

    /**
     * @param list<PriceListSummary> $priceLists
     */
    private function selected(array $priceLists, string $requestedId): ?PriceListSummary
    {
        if ([] === $priceLists) {
            return null;
        }

        foreach ($priceLists as $priceList) {
            if ('' !== $requestedId && $priceList->id === $requestedId) {
                return $priceList;
            }
        }

        foreach ($priceLists as $priceList) {
            if ($priceList->isDefault) {
                return $priceList;
            }
        }

        return $priceLists[0];
    }
}
