<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ChangeStockThreshold;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeStockThresholdHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeStockThreshold $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $stockItemId = StockItemId::fromString($command->stockItemId);

        $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

        if (null === $stockItem) {
            throw new StockItemNotFoundException($command->stockItemId);
        }

        $thresholdUnit = UnitOfMeasure::fromString($command->thresholdUnit);
        $threshold     = new StockThreshold(
            quantity: Quantity::of($command->thresholdAmount, $thresholdUnit),
            type: StockThresholdType::from($command->thresholdType),
        );

        $stockItem->changeThreshold($threshold, $this->clock->now());

        $this->stockItemRepository->save($stockItem);
        $this->domainEventPublisher->publish($stockItem);
    }
}
