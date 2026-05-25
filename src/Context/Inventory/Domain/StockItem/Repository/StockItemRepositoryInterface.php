<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Repository;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;

interface StockItemRepositoryInterface
{
    public function save(StockItem $stockItem): void;

    public function findById(StockItemId $id, ClinicId $clinicId): ?StockItem;

    public function findByClinicAndArticle(ClinicId $clinicId, ArticleId $articleId): ?StockItem;
}
