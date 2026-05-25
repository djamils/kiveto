<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\MarkLotAsRecalled;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MarkLotAsRecalledHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MarkLotAsRecalled $command): void
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

        $lotId = LotId::fromString($command->lotId);

        $recalledQty = $stockItem->markLotAsRecalled($lotId, $command->reason, $occurredAt, $now);

        $movement = null;

        if (!$recalledQty->isZero()) {
            $movementId = StockMovementId::fromString(Uuid::v7()->toRfc4122());

            $movement = StockMovement::record(
                id: $movementId,
                clinicId: $clinicId,
                articleId: $stockItem->articleId(),
                lotId: $lotId,
                type: MovementType::OUT,
                reason: MovementReason::RECALL_RETURN_TO_SUPPLIER,
                quantity: $recalledQty,
                occurredAt: $occurredAt,
                reference: null,
                performedBy: null,
                note: $command->reason,
                createdAt: $now,
            );
        }

        $capturedMovement = $movement;

        try {
            $this->entityManager->wrapInTransaction(function () use ($stockItem, $capturedMovement): void {
                $this->stockItemRepository->save($stockItem);

                if (null !== $capturedMovement) {
                    $this->stockMovementRepository->save($capturedMovement);
                }
            });
        } catch (ConcurrentStockModificationException $e) {
            throw $e;
        }

        $this->domainEventPublisher->publish($stockItem);

        if (null !== $movement) {
            $this->domainEventPublisher->publish($movement);
        }
    }
}
