<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\BulkImportInitialStock;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\Shared\ValueObject\SupplierId;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
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
final class BulkImportInitialStockHandler
{
    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        private readonly StockMovementRepositoryInterface $stockMovementRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(BulkImportInitialStock $command): BulkImportReport
    {
        $report = new BulkImportReport();

        foreach ($command->items as $item) {
            $ref = $item['articleId'];

            $stockItem = null;
            /** @var list<StockMovement> $movements */
            $movements = [];

            try {
                $clinicId  = ClinicId::fromString($item['clinicId']);
                $articleId = ArticleId::fromString($item['articleId']);

                $this->entityManager->wrapInTransaction(function () use (
                    $item,
                    $clinicId,
                    $articleId,
                    &$stockItem,
                    &$movements,
                    $report,
                    $ref,
                ): void {
                    $existing = $this->stockItemRepository->findByClinicAndArticle($clinicId, $articleId);

                    if (null !== $existing) {
                        $report->addSkipped($ref);

                        return;
                    }

                    $now  = $this->clock->now();
                    $unit = UnitOfMeasure::fromString($item['unit']);

                    $thresholdUnit = UnitOfMeasure::fromString($item['thresholdUnit']);
                    $threshold     = new StockThreshold(
                        quantity: Quantity::of($item['thresholdAmount'], $thresholdUnit),
                        type: StockThresholdType::from($item['thresholdType']),
                    );

                    $stockItemId = StockItemId::fromString(Uuid::v7()->toRfc4122());

                    $stockItem = StockItem::open(
                        id: $stockItemId,
                        clinicId: $clinicId,
                        articleId: $articleId,
                        unit: $unit,
                        threshold: $threshold,
                        trackStock: $item['trackStock'],
                        clock: $this->clock,
                    );

                    $this->stockItemRepository->save($stockItem);

                    foreach ($item['lots'] as $lotData) {
                        $newLotId = LotId::fromString(Uuid::v7()->toRfc4122());
                        $lotUnit  = UnitOfMeasure::fromString($lotData['quantityUnit']);
                        $lotQty   = Quantity::of($lotData['quantityAmount'], $lotUnit);
                        $expiry   = new \DateTimeImmutable($lotData['expiryDate']);

                        $supplier = isset($lotData['sourceSupplierId']) && '' !== $lotData['sourceSupplierId']
                            ? SupplierId::fromString($lotData['sourceSupplierId'])
                            : null;

                        $usedLotId = $stockItem->receiveStock(
                            lotNumber: LotNumber::fromString($lotData['lotNumber']),
                            quantity: $lotQty,
                            expiryDate: $expiry,
                            sourceSupplier: $supplier,
                            occurredAt: $now,
                            updatedAt: $now,
                            newLotId: $newLotId,
                        );

                        $movementId = StockMovementId::fromString(Uuid::v7()->toRfc4122());

                        $movement = StockMovement::record(
                            id: $movementId,
                            clinicId: $clinicId,
                            articleId: $articleId,
                            lotId: $usedLotId,
                            type: MovementType::IN,
                            reason: MovementReason::RECEIPT_OPENING,
                            quantity: $lotQty,
                            occurredAt: $now,
                            reference: null,
                            performedBy: null,
                            note: null,
                            createdAt: $now,
                        );

                        $this->stockMovementRepository->save($movement);
                        $movements[] = $movement;
                    }
                });

                if (null === $stockItem) {
                    // Item was skipped inside the transaction (duplicate)
                    continue;
                }

                $this->domainEventPublisher->publish($stockItem);

                foreach ($movements as $movement) {
                    $this->domainEventPublisher->publish($movement);
                }

                $report->addCreated($ref);
            } catch (\Throwable $e) {
                $report->addFailure($ref, $e);
            }
        }

        return $report;
    }
}
