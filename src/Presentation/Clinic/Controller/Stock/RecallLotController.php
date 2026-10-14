<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalled;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Rappeler le lot »: what is left of it goes back to the supplier and the
 * lot can no longer be used. The reason is kept — an inspection asks for it.
 */
#[Route(path: '/stock/items/{stockItemId}/lots/{lotId}/recall', name: 'clinic_stock_lot_recall', methods: ['POST'])]
final readonly class RecallLotController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $stockItemId, string $lotId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $reason = StockEndpoint::text($request, 'reason');

        if (null === $reason) {
            return $this->endpoint->refuse('Indiquez le motif du rappel.');
        }

        try {
            $this->commandBus->dispatch(new MarkLotAsRecalled(
                stockItemId: $stockItemId,
                clinicId: $this->endpoint->clinicId(),
                lotId: $lotId,
                reason: $reason,
                performedBy: $this->endpoint->performer(),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
