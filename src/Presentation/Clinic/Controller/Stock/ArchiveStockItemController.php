<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItem;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Arrêter le suivi » of an article whose shelf is empty. The domain refuses while stock remains — an article cannot leave the shelves by being forgotten. */
#[Route(path: '/stock/items/{stockItemId}/archive', name: 'clinic_stock_archive', methods: ['POST'])]
final readonly class ArchiveStockItemController
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
            $this->commandBus->dispatch(new ArchiveStockItem(stockItemId: $stockItemId, clinicId: $this->endpoint->clinicId()));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
