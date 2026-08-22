<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Query\GetActiveAlerts\GetActiveAlerts;
use App\Context\Inventory\Application\Query\StockOverview\ListLotsForStockItems;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Presentation\Clinic\ViewModel\Stock\StockPageContext;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What the clinic holds: every tracked article, with its figures.
 */
#[Route(path: '/stock', name: 'clinic_stock', methods: ['GET'])]
final class ListStockController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly StockPageContext $context,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $overview = $this->context->build($request, $clinicId);

        $alerts = $this->queryBus->ask(new GetActiveAlerts($clinicId));

        // The lots of the page in one read: the count and recall forms need
        // them, and firing a query per row would be a query per row.
        $stockItemIds = [];

        foreach ($overview['rows'] as $row) {
            if ($row['stock'] instanceof StockSnapshotRow) {
                $stockItemIds[] = $row['stock']->stockItemId;
            }
        }

        $lots = $this->queryBus->ask(new ListLotsForStockItems($clinicId, $stockItemIds));

        return $this->render('clinic/stock/index.html.twig', [
            'rows'               => $overview['rows'],
            'lots'               => \is_array($lots) ? $lots : [],
            'page'               => $overview['page'],
            'state'              => $overview['state'],
            'states'             => StockStateFilter::cases(),
            'alertCount'         => \is_array($alerts) ? \count($alerts) : 0,
            'pageSizes'          => StockPageContext::PAGE_SIZES,
            'queryState'         => $this->context->queryState($request),
            'expiringWithinDays' => StockPageContext::EXPIRING_WITHIN_DAYS,
        ]);
    }
}
