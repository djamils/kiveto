<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThreshold;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Seuil d'alerte » of the sheet: the quantity at or under which the clinic
 * wants to be warned. An article never received gets its stock item here.
 */
#[Route(path: '/stock/articles/{articleId}/threshold', name: 'clinic_stock_threshold', methods: ['POST'])]
final readonly class ChangeStockThresholdController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private StockEndpoint $endpoint,
        private StockItemResolver $items,
    ) {
    }

    public function __invoke(Request $request, string $articleId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $threshold = StockEndpoint::quantity($request->request->getString('threshold'));

        if (null === $threshold) {
            return $this->endpoint->refuse('Le seuil saisi est invalide.');
        }

        $clinicId = $this->endpoint->clinicId();

        try {
            $item = $this->items->findOrOpen($clinicId, $articleId);

            $this->commandBus->dispatch(new ChangeStockThreshold(
                stockItemId: $item['id'],
                clinicId: $clinicId,
                thresholdAmount: $threshold,
                thresholdUnit: $item['unit'],
                thresholdType: 'ABSOLUTE',
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
