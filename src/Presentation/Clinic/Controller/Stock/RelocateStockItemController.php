<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Inventory\Application\Command\RelocateStockItem\RelocateStockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Where the article is kept — chosen from the head of its sheet. */
#[Route(path: '/stock/articles/{articleId}/location', name: 'clinic_stock_location', methods: ['POST'])]
final readonly class RelocateStockItemController
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

        $raw      = $request->request->getString('location');
        $location = '' === $raw ? null : StorageLocation::tryFrom($raw);

        if ('' !== $raw && null === $location) {
            return $this->endpoint->refuse('Emplacement inconnu.');
        }

        $clinicId = $this->endpoint->clinicId();

        try {
            $item = $this->items->findOrOpen($clinicId, $articleId);

            $this->commandBus->dispatch(new RelocateStockItem($item['id'], $clinicId, $location?->value));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
