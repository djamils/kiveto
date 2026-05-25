<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockAlert\ReadModel;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;

final readonly class StockAlertView
{
    public function __construct(
        public string $alertId,
        public ClinicId $clinicId,
        public StockItemId $stockItemId,
        public ArticleId $articleId,
        public string $articleName,
        public StockAlertType $type,
        public StockAlertSeverity $severity,
        public ?string $currentLevelAmount,
        public ?string $currentLevelUnit,
        public ?\DateTimeImmutable $earliestExpiry,
        public \DateTimeImmutable $detectedAt,
        public ?\DateTimeImmutable $resolvedAt,
    ) {
    }
}
