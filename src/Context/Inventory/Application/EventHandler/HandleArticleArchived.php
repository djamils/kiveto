<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\EventHandler;

use App\Context\Catalog\Domain\Article\Event\ArticleArchived;
use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItem;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final class HandleArticleArchived
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly CommandBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ArticleArchived $event): void
    {
        $articleId = ArticleId::fromString($event->articleId);
        $clinicId  = ClinicId::fromString($event->clinicId);
        $stockItem = $this->stockItemRepository->findByClinicAndArticle($clinicId, $articleId);

        if (null === $stockItem) {
            return;
        }

        if (!$stockItem->totalOnHand()->isZero()) {
            $this->logger->warning('Cannot auto-archive StockItem: residual stock must be consumed first.', [
                'stockItemId' => $stockItem->id()->toString(),
                'articleId'   => $event->articleId,
                'totalOnHand' => $stockItem->totalOnHand()->toString(),
            ]);

            return;
        }

        $this->commandBus->dispatch(new ArchiveStockItem(
            stockItemId: $stockItem->id()->toString(),
            clinicId: $event->clinicId,
        ));
    }
}
