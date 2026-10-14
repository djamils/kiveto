<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Procurement\Application\Command\RemovePurchaseOrderLine\RemovePurchaseOrderLine;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockOrders;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Retirer » a line of the order being filled. */
#[Route(path: '/stock/orders/{purchaseOrderId}/lines/{lineId}/remove', name: 'clinic_stock_order_line_remove', methods: ['POST'])]
final readonly class RemoveOrderLineController
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

        $order = $this->orders->open($this->endpoint->clinicId(), $purchaseOrderId);

        if (null === $order) {
            return $this->endpoint->refuse('Cette commande n’est plus en cours.');
        }

        try {
            $this->commandBus->dispatch(new RemovePurchaseOrderLine($order->id, $lineId));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
