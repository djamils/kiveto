<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\DiscardExpiredLot\DiscardExpiredLot;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Sortir en perte » the remains of a lot past its date, from its alert. */
#[Route(path: '/stock/items/{stockItemId}/lots/{lotId}/discard', name: 'clinic_stock_lot_discard', methods: ['POST'])]
final readonly class DiscardExpiredLotController
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

        try {
            $this->commandBus->dispatch(new DiscardExpiredLot(
                stockItemId: $stockItemId,
                clinicId: $this->endpoint->clinicId(),
                lotId: $lotId,
                performedBy: $this->endpoint->performer(),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
