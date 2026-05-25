<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ExpireOutdatedLots;

use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ExpireOutdatedLotsHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockItemReadRepositoryInterface $stockItemReadRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpireOutdatedLots $command): int
    {
        $filterClinicId = null !== $command->clinicId
            ? ClinicId::fromString($command->clinicId)
            : null;

        $pairs = $this->stockItemReadRepository->findItemsWithExpiredLots($filterClinicId);

        $today          = $this->clock->now();
        $processedCount = 0;

        foreach ($pairs as $pair) {
            $stockItemId = StockItemId::fromString($pair['stockItemId']);
            $clinicId    = ClinicId::fromString($pair['clinicId']);

            $stockItem = $this->stockItemRepository->findById($stockItemId, $clinicId);

            if (null === $stockItem) {
                continue;
            }

            $stockItem->expireOutdatedLots($today, $today);

            /** @var list<StockMovement> $movements */
            $movements = [];

            foreach ($stockItem->recordedDomainEvents() as $event) {
                if (!($event instanceof LotExpired)) {
                    continue;
                }

                /** @var numeric-string $quantityAtExpiry */
                $quantityAtExpiry = $event->quantityAtExpiry;

                if (0 >= bccomp($quantityAtExpiry, '0', 6)) {
                    continue;
                }

                $movementId = StockMovementId::fromString(Uuid::v7()->toRfc4122());
                $lotUnit    = UnitOfMeasure::fromString($event->quantityUnit);
                $qty        = Quantity::of($quantityAtExpiry, $lotUnit);

                $movements[] = StockMovement::record(
                    id: $movementId,
                    clinicId: $clinicId,
                    articleId: $stockItem->articleId(),
                    lotId: LotId::fromString($event->lotId),
                    type: MovementType::OUT,
                    reason: MovementReason::LOSS_EXPIRY,
                    quantity: $qty,
                    occurredAt: $today,
                    reference: null,
                    performedBy: null,
                    note: null,
                    createdAt: $today,
                );
            }

            $capturedMovements = $movements;

            try {
                $this->entityManager->wrapInTransaction(function () use ($stockItem, $capturedMovements): void {
                    $this->stockItemRepository->save($stockItem);

                    foreach ($capturedMovements as $movement) {
                        $this->stockMovementRepository->save($movement);
                    }
                });
            } catch (ConcurrentStockModificationException $e) {
                $this->logger->warning('Skipping concurrent modification on StockItem during lot expiry batch.', [
                    'stockItemId' => $pair['stockItemId'],
                    'error'       => $e->getMessage(),
                ]);

                continue;
            }

            $this->domainEventPublisher->publish($stockItem);

            foreach ($movements as $movement) {
                $this->domainEventPublisher->publish($movement);
            }

            ++$processedCount;
        }

        return $processedCount;
    }
}
