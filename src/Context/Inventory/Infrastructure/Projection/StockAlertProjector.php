<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Projection;

use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\Event\LotAdded;
use App\Context\Inventory\Domain\StockItem\Event\LotDepleted;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Event\LotRecalled;
use App\Context\Inventory\Domain\StockItem\Event\StockAdjusted;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Event\StockReceived;
use App\Context\Inventory\Domain\StockItem\Event\StockThresholdChanged;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final class StockAlertProjector
{
    private const int CRITICAL_DAYS = 7;
    private const int WARNING_DAYS  = 14;
    private const int INFO_DAYS     = 30;

    public function __construct(
        private readonly Connection $connection,
        private readonly StockAlertReadRepositoryInterface $alertRepository,
    ) {
    }

    #[AsMessageHandler]
    public function onLotAdded(LotAdded $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowExpiry($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onStockReceived(StockReceived $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowStock($stockItemId, $event->stockItemId);
        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onStockConsumed(StockConsumed $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowStock($stockItemId, $event->stockItemId);
        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onStockAdjusted(StockAdjusted $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowStock($stockItemId, $event->stockItemId);
        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onLotExpired(LotExpired $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowExpiry($stockItemId, $event->stockItemId);
        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onLotDepleted(LotDepleted $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onStockThresholdChanged(StockThresholdChanged $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowStock($stockItemId, $event->stockItemId);
    }

    #[AsMessageHandler]
    public function onLotRecalled(LotRecalled $event): void
    {
        $stockItemId = StockItemId::fromString($event->stockItemId);

        if ($this->isTrackingDisabled($event->stockItemId)) {
            return;
        }

        $this->reevaluateLowExpiry($stockItemId, $event->stockItemId);
        $this->reevaluateOutOfStock($stockItemId, $event->stockItemId);
    }

    // -------------------------------------------------------------------------

    private function isTrackingDisabled(string $stockItemIdStr): bool
    {
        $row = $this->connection->fetchOne(
            'SELECT track_stock FROM inventory__stock_items WHERE id = :id',
            ['id' => Uuid::fromString($stockItemIdStr)->toBinary()]
        );

        return false === $row || !(bool) $row;
    }

    private function reevaluateLowExpiry(StockItemId $stockItemId, string $stockItemIdStr): void
    {
        $now = new \DateTimeImmutable();
        $row = $this->connection->fetchAssociative(
            "SELECT MIN(expiry_date) AS min_expiry FROM inventory__lots WHERE stock_item_id = :id AND status = 'ACTIVE'",
            ['id' => Uuid::fromString($stockItemIdStr)->toBinary()]
        );

        $minExpiry      = ($row && $row['min_expiry']) ? new \DateTimeImmutable(RowAccessor::string($row, 'min_expiry')) : null;
        $existingAlert  = $this->alertRepository->findByStockItemAndType($stockItemId, StockAlertType::LOW_EXPIRY);
        $severity       = null;
        $earliestExpiry = null;

        if (null !== $minExpiry) {
            $daysUntilExpiry = (int) $now->diff($minExpiry)->days;
            $earliestExpiry  = $minExpiry;

            if ($daysUntilExpiry <= self::CRITICAL_DAYS) {
                $severity = StockAlertSeverity::CRITICAL;
            } elseif ($daysUntilExpiry <= self::WARNING_DAYS) {
                $severity = StockAlertSeverity::WARNING;
            } elseif ($daysUntilExpiry <= self::INFO_DAYS) {
                $severity = StockAlertSeverity::INFO;
            }
        }

        $this->applyAlertMatrix(
            $stockItemId,
            $stockItemIdStr,
            StockAlertType::LOW_EXPIRY,
            $severity,
            $existingAlert,
            $now,
            null,
            $earliestExpiry,
        );
    }

    private function reevaluateLowStock(StockItemId $stockItemId, string $stockItemIdStr): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT total_on_hand_amount, threshold_amount FROM inventory__stock_items WHERE id = :id',
            ['id' => Uuid::fromString($stockItemIdStr)->toBinary()]
        );

        if (!$row) {
            return;
        }

        /** @var numeric-string $totalOnHand */
        $totalOnHand = RowAccessor::string($row, 'total_on_hand_amount');
        /** @var numeric-string $threshold */
        $threshold        = RowAccessor::string($row, 'threshold_amount');
        $isBelowThreshold = bccomp($totalOnHand, $threshold, 6) < 0;
        $existingAlert    = $this->alertRepository->findByStockItemAndType($stockItemId, StockAlertType::LOW_STOCK);
        $severity         = $isBelowThreshold ? StockAlertSeverity::WARNING : null;

        $this->applyAlertMatrix(
            $stockItemId,
            $stockItemIdStr,
            StockAlertType::LOW_STOCK,
            $severity,
            $existingAlert,
            new \DateTimeImmutable(),
            $totalOnHand,
            null,
        );
    }

    private function reevaluateOutOfStock(StockItemId $stockItemId, string $stockItemIdStr): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT total_on_hand_amount, reserved_amount FROM inventory__stock_items WHERE id = :id',
            ['id' => Uuid::fromString($stockItemIdStr)->toBinary()]
        );

        if (!$row) {
            return;
        }

        /** @var numeric-string $totalOnHandAmount */
        $totalOnHandAmount = RowAccessor::string($row, 'total_on_hand_amount');
        /** @var numeric-string $reservedAmount */
        $reservedAmount  = RowAccessor::string($row, 'reserved_amount');
        $availableAmount = bcsub($totalOnHandAmount, $reservedAmount, 6);
        $isOutOfStock    = 0 === bccomp($availableAmount, '0', 6);
        $existingAlert   = $this->alertRepository->findByStockItemAndType($stockItemId, StockAlertType::OUT_OF_STOCK);
        $severity        = $isOutOfStock ? StockAlertSeverity::CRITICAL : null;

        $this->applyAlertMatrix(
            $stockItemId,
            $stockItemIdStr,
            StockAlertType::OUT_OF_STOCK,
            $severity,
            $existingAlert,
            new \DateTimeImmutable(),
            $availableAmount,
            null,
        );
    }

    private function applyAlertMatrix(
        StockItemId $stockItemId,
        string $stockItemIdStr,
        StockAlertType $type,
        ?StockAlertSeverity $severity,
        ?StockAlertView $existingAlert,
        \DateTimeImmutable $now,
        ?string $currentLevelAmount,
        ?\DateTimeImmutable $earliestExpiry,
    ): void {
        if (null === $severity) {
            if (null !== $existingAlert && null === $existingAlert->resolvedAt) {
                $this->alertRepository->resolve($existingAlert->alertId, $now);
            }

            return;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT clinic_id, article_id, total_on_hand_unit FROM inventory__stock_items WHERE id = :id',
            ['id' => Uuid::fromString($stockItemIdStr)->toBinary()]
        );

        if (!$row) {
            return;
        }

        $clinicIdStr  = Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString();
        $articleIdStr = Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString();
        $unit         = RowAccessor::string($row, 'total_on_hand_unit');
        $articleName  = $this->fetchArticleName($articleIdStr);

        if (null === $existingAlert) {
            $this->alertRepository->save(new StockAlertView(
                alertId: Uuid::v7()->toRfc4122(),
                clinicId: ClinicId::fromString($clinicIdStr),
                stockItemId: $stockItemId,
                articleId: ArticleId::fromString($articleIdStr),
                articleName: $articleName,
                type: $type,
                severity: $severity,
                currentLevelAmount: $currentLevelAmount,
                currentLevelUnit: $unit,
                earliestExpiry: $earliestExpiry,
                detectedAt: $now,
                resolvedAt: null,
            ));

            return;
        }

        if (null === $existingAlert->resolvedAt) {
            $this->alertRepository->save(new StockAlertView(
                alertId: $existingAlert->alertId,
                clinicId: $existingAlert->clinicId,
                stockItemId: $existingAlert->stockItemId,
                articleId: $existingAlert->articleId,
                articleName: $existingAlert->articleName,
                type: $existingAlert->type,
                severity: $severity,
                currentLevelAmount: $currentLevelAmount ?? $existingAlert->currentLevelAmount,
                currentLevelUnit: $unit,
                earliestExpiry: $earliestExpiry ?? $existingAlert->earliestExpiry,
                detectedAt: $now,
                resolvedAt: null,
            ));

            return;
        }

        // Resolved alert + condition true again — insert a new alert
        $this->alertRepository->save(new StockAlertView(
            alertId: Uuid::v7()->toRfc4122(),
            clinicId: ClinicId::fromString($clinicIdStr),
            stockItemId: $stockItemId,
            articleId: ArticleId::fromString($articleIdStr),
            articleName: $articleName,
            type: $type,
            severity: $severity,
            currentLevelAmount: $currentLevelAmount,
            currentLevelUnit: $unit,
            earliestExpiry: $earliestExpiry,
            detectedAt: $now,
            resolvedAt: null,
        ));
    }

    private function fetchArticleName(string $articleIdStr): string
    {
        try {
            $row = $this->connection->fetchOne(
                'SELECT name FROM catalog__articles WHERE id = :id LIMIT 1',
                ['id' => Uuid::fromString($articleIdStr)->toBinary()]
            );

            return \is_string($row) ? $row : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
