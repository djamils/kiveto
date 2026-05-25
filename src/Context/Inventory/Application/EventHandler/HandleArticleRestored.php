<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\EventHandler;

use App\Context\Catalog\Domain\Article\Event\ArticleRestored;
use App\Context\Inventory\Application\Command\RestoreStockItem\RestoreStockItem;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final class HandleArticleRestored
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ArticleRestored $event): void
    {
        $articleId = ArticleId::fromString($event->articleId);
        $clinicId  = ClinicId::fromString($event->clinicId);
        $stockItem = $this->stockItemRepository->findByClinicAndArticle($clinicId, $articleId);

        if (null === $stockItem) {
            return;
        }

        $this->commandBus->dispatch(new RestoreStockItem(
            stockItemId: $stockItem->id()->toString(),
            clinicId: $event->clinicId,
        ));
    }
}
