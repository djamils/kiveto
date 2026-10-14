<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine\UpdatePurchaseOrderLine;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockOrders;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** The number of packagings of a line of the order being filled (− / + / typed). */
#[Route(path: '/stock/orders/{purchaseOrderId}/lines/{lineId}', name: 'clinic_stock_order_line_update', methods: ['POST'])]
final readonly class UpdateOrderLineController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockOrders $orders,
    ) {
    }

    public function __invoke(Request $request, string $purchaseOrderId, string $lineId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $packs = StockEndpoint::quantity($request->request->getString('n'));
        $order = $this->orders->open($this->endpoint->clinicId(), $purchaseOrderId);
        $line  = null;

        foreach ($order->lines ?? [] as $candidate) {
            $line = $candidate->id === $lineId ? $candidate : $line;
        }

        if (null === $order || null === $line) {
            return $this->endpoint->refuse('Cette ligne n’est plus dans la commande.');
        }

        if (null === $packs || 0 >= bccomp($packs, '0', 6)) {
            return $this->endpoint->refuse('Quantité à commander invalide.');
        }

        try {
            $this->commandBus->dispatch(new UpdatePurchaseOrderLine(
                purchaseOrderId: $order->id,
                lineId: $line->id,
                orderedAmount: $packs,
                unitPriceMinor: $line->unitPriceMinor,
                unitPriceCurrency: $order->currency,
                note: null,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
