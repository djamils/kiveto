<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Procurement\Application\Command\SubmitPurchaseOrder\SubmitPurchaseOrder;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockOrders;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Envoyer à … »: the order being filled goes to its supplier through the
 * supplier's integration. A send the supplier refused leaves the order
 * « échec d'envoi » and says so.
 */
#[Route(path: '/stock/orders/{purchaseOrderId}/submit', name: 'clinic_stock_order_submit', methods: ['POST'])]
final readonly class SubmitOrderController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockOrders $orders,
    ) {
    }

    public function __invoke(Request $request, string $purchaseOrderId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();
        $order    = $this->orders->open($clinicId, $purchaseOrderId);

        if (null === $order || !$order->isDraft()) {
            return $this->endpoint->refuse('Cette commande n’est plus en cours.');
        }

        try {
            $this->commandBus->dispatch(new SubmitPurchaseOrder($order->id, $clinicId));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            return $this->endpoint->refuse('Ce fournisseur ne peut pas recevoir de commande depuis Kiveto pour le moment.');
        }

        $sent = $this->orders->open($clinicId, $order->id);

        if ('SEND_FAILED' === $sent?->status) {
            return $this->endpoint->refuse(\sprintf('L’envoi à %s a échoué : la commande reste à renvoyer.', $order->supplierName));
        }

        // A supplier taking its orders by file gets one to download and send.
        return $this->endpoint->ok(['numero' => $order->orderNumber, 'fichier' => null !== $sent?->exportFile]);
    }
}
