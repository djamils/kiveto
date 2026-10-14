<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RestoreStockItem\RestoreStockItem;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Reprendre le suivi » of an article whose follow-up was stopped. */
#[Route(path: '/stock/items/{stockItemId}/restore', name: 'clinic_stock_restore', methods: ['POST'])]
final readonly class RestoreStockItemController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $stockItemId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        try {
            $this->commandBus->dispatch(new RestoreStockItem(stockItemId: $stockItemId, clinicId: $this->endpoint->clinicId()));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
