<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ReceiveStock;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\Shared\ValueObject\SupplierId;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
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
final class ReceiveStockHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ReceiveStock $command): void
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
        $expiry   = new \DateTimeImmutable($command->expiryDate);

        $supplier = null !== $command->sourceSupplierId
            ? SupplierId::fromString($command->sourceSupplierId)
            : null;

        $newLotId = LotId::fromString(Uuid::v7()->toRfc4122());

        $usedLotId = $stockItem->receiveStock(
            lotNumber: LotNumber::fromString($command->lotNumber),
            quantity: $quantity,
            expiryDate: $expiry,
            sourceSupplier: $supplier,
            occurredAt: $occurredAt,
            updatedAt: $now,
            newLotId: $newLotId,
        );

        $reason = null !== $command->sourceSupplierId
            ? MovementReason::RECEIPT_SUPPLIER
            : MovementReason::RECEIPT_OPENING;

        $movementId = StockMovementId::fromString(Uuid::v7()->toRfc4122());

        $movement = StockMovement::record(
            id: $movementId,
            clinicId: $clinicId,
            articleId: $stockItem->articleId(),
            lotId: $usedLotId,
            type: MovementType::IN,
            reason: $reason,
            quantity: $quantity,
            occurredAt: $occurredAt,
            reference: $command->reference,
            performedBy: $command->performedBy,
            note: null,
            createdAt: $now,
        );

        try {
            $this->entityManager->wrapInTransaction(function () use ($stockItem, $movement): void {
                $this->stockItemRepository->save($stockItem);
                $this->stockMovementRepository->save($movement);
            });
        } catch (ConcurrentStockModificationException $e) {
            throw $e;
        }

        $this->domainEventPublisher->publish($stockItem);
        $this->domainEventPublisher->publish($movement);
    }
}
