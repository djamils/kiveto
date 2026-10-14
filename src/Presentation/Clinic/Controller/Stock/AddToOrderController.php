<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Procurement\Application\Command\AddPurchaseOrderLine\AddPurchaseOrderLine;
use App\Context\Procurement\Application\Command\CreatePurchaseOrder\CreatePurchaseOrder;
use App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine\UpdatePurchaseOrderLine;
use App\Presentation\Clinic\ViewModel\Stock\StockArticles;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockOrders;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Ajouter à la commande » — from an alert, the sheet, the drawer's
 * suggestions or a selection: each article goes into the order being filled
 * with the supplier that sells it (opened on the way), for the number of
 * packagings asked. An article already in it takes the new number. The
 * answer says what each line held before, for the page's « Annuler ».
 */
#[Route(path: '/stock/orders/lines', name: 'clinic_stock_order_add', methods: ['POST'])]
final readonly class AddToOrderController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockArticles $articles,
        private StockOrders $orders,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();
        $articles = $this->articles->byId($clinicId);
        $undo     = [];

        try {
            foreach (StockEndpoint::rows($request, 'lines') as $row) {
                $article = $articles[$row['art'] ?? ''] ?? null;
                $packs   = StockEndpoint::quantity($row['n'] ?? '');

                if (null === $article || null === $packs || 0 >= bccomp($packs, '0', 6)) {
                    return $this->endpoint->refuse('Quantité à commander invalide.');
                }

                $offer = $this->orders->offer($clinicId, $article);

                if (null === $offer) {
                    return $this->endpoint->refuse(\sprintf('Aucun fournisseur de la clinique ne référence « %s ».', $article->name));
                }

                $draft   = $this->orders->draftFor($clinicId, $offer->supplierId);
                $orderId = $draft?->id;

                if (null === $orderId) {
                    $orderId = $this->commandBus->dispatch(new CreatePurchaseOrder(
                        clinicId: $clinicId,
                        supplierId: $offer->supplierId,
                        supplierAccountId: $offer->supplierAccountId,
                        deliveryAddress: [],
                    ));
                    \assert(\is_string($orderId));
                }

                $existing = null;

                foreach ($draft->lines ?? [] as $line) {
                    if ($line->articleId === $article->id && 'ACTIVE' === $line->status) {
                        $existing = $line;
                    }
                }

                if (null !== $existing) {
                    $this->commandBus->dispatch(new UpdatePurchaseOrderLine(
                        purchaseOrderId: $orderId,
                        lineId: $existing->id,
                        orderedAmount: $packs,
                        unitPriceMinor: $existing->unitPriceMinor,
                        unitPriceCurrency: $offer->currency,
                        note: null,
                    ));
                    $undo[] = ['art' => $article->id, 'order' => $orderId, 'line' => $existing->id, 'n' => $existing->orderedAmount];

                    continue;
                }

                $this->commandBus->dispatch(new AddPurchaseOrderLine(
                    purchaseOrderId: $orderId,
                    clinicId: $clinicId,
                    articleId: $article->id,
                    catalogEntryId: $offer->catalogEntryId,
                    orderedAmount: $packs,
                    orderedUnit: $offer->packagingUnit ?? $article->unitOfMeasure,
                    unitPriceMinor: $offer->priceMinor,
                    unitPriceCurrency: $offer->currency,
                    note: null,
                ));
                $undo[] = ['art' => $article->id, 'order' => $orderId, 'line' => null, 'n' => null];
            }
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['undo' => $undo]);
    }
}
