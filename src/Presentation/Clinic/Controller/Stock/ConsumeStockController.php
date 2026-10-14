<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Sortie de stock »: what leaves the shelf without going through an
 * invoice — a counter sale, internal use, a loss, a return — out of the lot
 * that was actually taken. The answer carries the lot's quantity before, so
 * the page can offer its « Annuler ».
 */
#[Route(path: '/stock/items/{stockItemId}/withdrawals', name: 'clinic_stock_consume', methods: ['POST'])]
final readonly class ConsumeStockController
{
    /** The motifs of the window, as the trail records them. */
    private const array REASONS = [
        'SALE_RETAIL', 'CONSUMPTION_INTERNAL', 'CONSUMPTION_CONSULTATION', 'LOSS_EXPIRY', 'LOSS_BREAKAGE', 'LOSS_THEFT', 'RETURN_TO_SUPPLIER',
    ];

    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockItemResolver $items,
    ) {
    }

    public function __invoke(Request $request, string $stockItemId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $quantity = StockEndpoint::quantity($request->request->getString('quantity'));
        $lotId    = $request->request->getString('lot');
        $reason   = $request->request->getString('reason');

        if (null === $quantity || 0 >= bccomp($quantity, '0', 6)) {
            return $this->endpoint->refuse('La quantité sortie est invalide.');
        }

        if (!\in_array($reason, self::REASONS, true)) {
            return $this->endpoint->refuse('Choisissez le motif de la sortie.');
        }

        $clinicId = $this->endpoint->clinicId();

        try {
            $lots = $this->items->lots($clinicId, $stockItemId);

            if (!isset($lots[$lotId])) {
                return $this->endpoint->refuse('Choisissez le lot sorti.');
            }

            $this->commandBus->dispatch(new ConsumeStock(
                stockItemId: $stockItemId,
                clinicId: $clinicId,
                quantityAmount: $quantity,
                quantityUnit: $lots[$lotId]->unit,
                occurredAt: null,
                reference: StockEndpoint::text($request, 'reference'),
                performedBy: $this->endpoint->performer(),
                note: StockEndpoint::text($request, 'note'),
                reason: $reason,
                lotId: $lotId,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['undo' => [['item' => $stockItemId, 'lot' => $lotId, 'q' => $lots[$lotId]->quantity]]]);
    }
}
