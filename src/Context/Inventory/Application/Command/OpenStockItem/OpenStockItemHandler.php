<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\OpenStockItem;

use App\Context\Inventory\Application\Exception\ArticleNotActiveException;
use App\Context\Inventory\Application\Exception\ArticleNotFoundException;
use App\Context\Inventory\Application\Port\ArticleProviderInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\DuplicateStockItemException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class OpenStockItemHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly ArticleProviderInterface $articleProvider,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenStockItem $command): string
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $articleId = ArticleId::fromString($command->articleId);
        $unit      = UnitOfMeasure::fromString($command->unit);

        if (!$this->articleProvider->exists($articleId, $clinicId)) {
            throw new ArticleNotFoundException($command->articleId, $command->clinicId);
        }

        if (!$this->articleProvider->isActive($articleId, $clinicId)) {
            throw new ArticleNotActiveException($command->articleId, $command->clinicId);
        }

        $existing = $this->stockItemRepository->findByClinicAndArticle($clinicId, $articleId);

        if (null !== $existing) {
            throw new DuplicateStockItemException($command->clinicId, $command->articleId);
        }

        $thresholdUnit = UnitOfMeasure::fromString($command->thresholdUnit);
        $threshold     = new StockThreshold(
            quantity: Quantity::of($command->thresholdAmount, $thresholdUnit),
            type: StockThresholdType::from($command->thresholdType),
        );

        $stockItemId = StockItemId::fromString(Uuid::v7()->toRfc4122());

        $stockItem = StockItem::open(
            id: $stockItemId,
            clinicId: $clinicId,
            articleId: $articleId,
            unit: $unit,
            threshold: $threshold,
            trackStock: $command->trackStock,
            clock: $this->clock,
        );

        $this->stockItemRepository->save($stockItem);
        $this->domainEventPublisher->publish($stockItem);

        return $stockItemId->toString();
    }
}
