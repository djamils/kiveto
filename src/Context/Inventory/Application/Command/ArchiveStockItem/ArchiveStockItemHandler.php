<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ArchiveStockItem;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ArchiveStockItemHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveStockItem $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $stockItemId = StockItemId::fromString($command->stockItemId);

        $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

        if (null === $stockItem) {
            throw new StockItemNotFoundException($command->stockItemId);
        }

        $stockItem->archive($this->clock->now());

        $this->stockItemRepository->save($stockItem);
        $this->domainEventPublisher->publish($stockItem);
    }
}
