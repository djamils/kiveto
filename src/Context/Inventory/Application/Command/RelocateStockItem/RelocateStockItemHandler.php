<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\RelocateStockItem;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RelocateStockItemHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RelocateStockItem $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $stockItemId = StockItemId::fromString($command->stockItemId);
        $location    = null !== $command->location ? StorageLocation::from($command->location) : null;

        $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

        if (null === $stockItem) {
            throw new StockItemNotFoundException($command->stockItemId);
        }

        $stockItem->relocate($location, $this->clock->now());

        $this->stockItemRepository->save($stockItem);
        $this->domainEventPublisher->publish($stockItem);
    }
}
