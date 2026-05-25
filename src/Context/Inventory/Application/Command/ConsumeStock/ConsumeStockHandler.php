<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ConsumeStock;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\Service\FefoSelector;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotConsumption;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ConsumeStockHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
        private readonly FefoSelector $fefoSelector,
    ) {
    }

    public function __invoke(ConsumeStock $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $stockItemId = StockItemId::fromString($command->stockItemId);

        $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

        if (null === $stockItem) {
            throw new StockItemNotFoundException($command->stockItemId);
        }

        $now = $this->clock->now();

        $occurredAt = null !== $command->occurredAt
            ? new \DateTimeImmutable($command->occurredAt)
            : $now;

        $unit     = UnitOfMeasure::fromString($command->quantityUnit);
        $quantity = Quantity::of($command->quantityAmount, $unit);
        $reason   = MovementReason::from($command->reason);

        /** @var list<LotConsumption> $lotConsumptions */
        $lotConsumptions = $stockItem->consumeStock(
            quantity: $quantity,
            fefoSelector: $this->fefoSelector,
            occurredAt: $occurredAt,
            updatedAt: $now,
        );

        if ([] === $lotConsumptions) {
            // trackStock=false: save StockItem for updatedAt, no movements
            $this->stockItemRepository->save($stockItem);
            $this->domainEventPublisher->publish($stockItem);

            return;
        }

        /** @var list<StockMovement> $movements */
        $movements = [];

        foreach ($lotConsumptions as $lc) {
            $movementId = StockMovementId::fromString(Uuid::v7()->toRfc4122());

            $movements[] = StockMovement::record(
                id: $movementId,
                clinicId: $clinicId,
                articleId: $stockItem->articleId(),
                lotId: $lc->lotId,
                type: MovementType::OUT,
                reason: $reason,
                quantity: $lc->quantity,
                occurredAt: $occurredAt,
                reference: $command->reference,
                performedBy: $command->performedBy,
                note: $command->note,
                createdAt: $now,
            );
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($stockItem, $movements): void {
                $this->stockItemRepository->save($stockItem);

                foreach ($movements as $movement) {
                    $this->stockMovementRepository->save($movement);
                }
            });
        } catch (ConcurrentStockModificationException $e) {
            throw $e;
        }

        $this->domainEventPublisher->publish($stockItem);

        foreach ($movements as $movement) {
            $this->domainEventPublisher->publish($movement);
        }
    }
}
