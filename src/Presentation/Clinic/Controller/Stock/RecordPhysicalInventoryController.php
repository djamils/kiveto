<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventory;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Inventaire »: lots counted, and what is on the shelf wins. Every gap is
 * recorded as an adjustment under the count's reference rather than silently
 * absorbed; a lot counted as expected leaves no trace but the count's date.
 * The answer carries what each lot held before, for the page's « Annuler ».
 */
#[Route(path: '/stock/counts', name: 'clinic_stock_count', methods: ['POST'])]
final readonly class RecordPhysicalInventoryController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockItemResolver $items,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $counts = StockEndpoint::rows($request, 'counts');

        if ([] === $counts) {
            return $this->endpoint->refuse('Saisissez au moins un comptage.');
        }

        $clinicId  = $this->endpoint->clinicId();
        $reference = 'INV-' . $this->clock->now()->format('Y-m-d');
        $undo      = [];

        try {
            foreach ($counts as $count) {
                $quantity    = StockEndpoint::quantity($count['q'] ?? '');
                $stockItemId = $count['item'] ?? '';
                $lots        = $this->items->lots($clinicId, $stockItemId);
                $lot         = $lots[$count['lot'] ?? ''] ?? null;

                if (null === $quantity || null === $lot) {
                    return $this->endpoint->refuse('Un comptage est invalide.');
                }

                $this->commandBus->dispatch(new RecordPhysicalInventory(
                    stockItemId: $stockItemId,
                    clinicId: $clinicId,
                    lotId: $lot->id,
                    newLotQuantityAmount: $quantity,
                    newLotQuantityUnit: $lot->unit,
                    reason: 'PHYSICAL_INVENTORY',
                    performedBy: $this->endpoint->performer(),
                    reference: $reference,
                ));

                $undo[] = ['item' => $stockItemId, 'lot' => $lot->id, 'q' => $lot->quantity];
            }
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['undo' => $undo, 'reference' => $reference]);
    }
}
