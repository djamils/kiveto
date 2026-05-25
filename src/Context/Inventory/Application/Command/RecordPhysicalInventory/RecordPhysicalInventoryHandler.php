<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\RecordPhysicalInventory;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Event\StockAdjusted;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
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
final class RecordPhysicalInventoryHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RecordPhysicalInventory $command): void
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

        $lotId  = LotId::fromString($command->lotId);
        $unit   = UnitOfMeasure::fromString($command->newLotQuantityUnit);
        $newQty = Quantity::of($command->newLotQuantityAmount, $unit);
        $reason = MovementReason::from($command->reason);

        $stockItem->adjustStock($lotId, $newQty, $command->reason, $occurredAt, $now);

        // Inspect recorded events to extract the delta amount without clearing events
        $adjustedEvent = null;

        foreach ($stockItem->recordedDomainEvents() as $event) {
            if ($event instanceof StockAdjusted) {
                $adjustedEvent = $event;
            }
        }

        /** @var numeric-string $deltaAmount */
        $deltaAmount = null !== $adjustedEvent ? $adjustedEvent->deltaAmount : '0.000000';
        /** @var numeric-string $absAmount */
        $absAmount = ltrim($deltaAmount, '-');

        $movement = null;

        if (0 !== bccomp($absAmount, '0', 6)) {
            $movementType = match (true) {
                bccomp($deltaAmount, '0', 6) > 0 => MovementType::IN,
                bccomp($deltaAmount, '0', 6) < 0 => MovementType::OUT,
                default                          => MovementType::ADJUSTMENT,
            };

            // PHYSICAL_INVENTORY and CORRECTION must use ADJUSTMENT type
            if (MovementReason::PHYSICAL_INVENTORY === $reason || MovementReason::CORRECTION === $reason) {
                $movementType = MovementType::ADJUSTMENT;
            }

            $movementId  = StockMovementId::fromString(Uuid::v7()->toRfc4122());
            $movementQty = Quantity::of($absAmount, $unit);

            $movement = StockMovement::record(
                id: $movementId,
                clinicId: $clinicId,
                articleId: $stockItem->articleId(),
                lotId: $lotId,
                type: $movementType,
                reason: $reason,
                quantity: $movementQty,
                occurredAt: $occurredAt,
                reference: null,
                performedBy: $command->performedBy,
                note: null,
                createdAt: $now,
            );
        }

        $capturedMovement = $movement;

        $this->entityManager->wrapInTransaction(function () use ($stockItem, $capturedMovement): void {
            $this->stockItemRepository->save($stockItem);

            if (null !== $capturedMovement) {
                $this->stockMovementRepository->save($capturedMovement);
            }
        });

        $this->domainEventPublisher->publish($stockItem);

        if (null !== $movement) {
            $this->domainEventPublisher->publish($movement);
        }
    }
}
