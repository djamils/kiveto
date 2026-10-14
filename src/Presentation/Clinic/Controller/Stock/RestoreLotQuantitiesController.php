<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventory;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The « Annuler » of a withdrawal, a receipt or a count. The trail is
 * append-only: nothing is erased, each lot is brought back to what it held
 * with a « Correction » adjustment, visible in the trail like any movement.
 */
#[Route(path: '/stock/lots/restore', name: 'clinic_stock_lots_restore', methods: ['POST'])]
final readonly class RestoreLotQuantitiesController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockItemResolver $items,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();

        try {
            foreach (StockEndpoint::rows($request, 'lots') as $row) {
                $quantity    = StockEndpoint::quantity($row['q'] ?? '');
                $stockItemId = $row['item'] ?? '';
                $lot         = $this->items->lots($clinicId, $stockItemId)[$row['lot'] ?? ''] ?? null;

                if (null === $quantity || null === $lot) {
                    return $this->endpoint->refuse('Ce mouvement ne peut plus être annulé.');
                }

                $this->commandBus->dispatch(new RecordPhysicalInventory(
                    stockItemId: $stockItemId,
                    clinicId: $clinicId,
                    lotId: $lot->id,
                    newLotQuantityAmount: $quantity,
                    newLotQuantityUnit: $lot->unit,
                    reason: 'CORRECTION',
                    performedBy: $this->endpoint->performer(),
                    reference: 'Annulation',
                ));
            }
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
