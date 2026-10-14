<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\DiscardExpiredLot;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
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

/**
 * The trail keeps one « Perte — péremption » per lot: written when the lot
 * expires. A lot the nightly expiry had not reached yet is expired here, and
 * the loss is written now; one it had reached already left the usable stock
 * then, and clearing the shelf moves nothing.
 */
#[AsMessageHandler]
final readonly class DiscardExpiredLotHandler
{
    public function __construct(
        private StockItemRepositoryInterface $stockItemRepository,
        private StockMovementRepositoryInterface $stockMovementRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DiscardExpiredLot $command): void
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $stockItemId = StockItemId::fromString($command->stockItemId);

        $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

        if (null === $stockItem) {
            throw new StockItemNotFoundException($command->stockItemId);
        }

        $now   = $this->clock->now();
        $lotId = LotId::fromString($command->lotId);

        $expiredNow = $stockItem->discardExpiredLot($lotId, $now, $now);

        $movement = $expiredNow->isZero() ? null : StockMovement::record(
            id: StockMovementId::fromString(Uuid::v7()->toRfc4122()),
            clinicId: $clinicId,
            articleId: $stockItem->articleId(),
            lotId: $lotId,
            type: MovementType::OUT,
            reason: MovementReason::LOSS_EXPIRY,
            quantity: $expiredNow,
            occurredAt: $now,
            reference: null,
            performedBy: $command->performedBy,
            note: null,
            createdAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($stockItem, $movement): void {
            $this->stockItemRepository->save($stockItem);

            if (null !== $movement) {
                $this->stockMovementRepository->save($movement);
            }
        });

        $this->domainEventPublisher->publish($stockItem);

        if (null !== $movement) {
            $this->domainEventPublisher->publish($movement);
        }
    }
}
