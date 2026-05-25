<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Uid\Uuid;

final readonly class StockItemMapper
{
    public function __construct(private LotMapper $lotMapper)
    {
    }

    public function toEntity(StockItem $stockItem): StockItemEntity
    {
        $entity = new StockItemEntity();

        $entity->setId(Uuid::fromString($stockItem->id()->toString()));
        $entity->setClinicId(Uuid::fromString($stockItem->clinicId()->toString()));
        $entity->setArticleId(Uuid::fromString($stockItem->articleId()->toString()));
        $entity->setTotalOnHandAmount($stockItem->totalOnHand()->amount());
        $entity->setTotalOnHandUnit($stockItem->totalOnHand()->unit()->toString());
        $entity->setReservedAmount($stockItem->reservedQuantity()->amount());
        $entity->setThresholdAmount($stockItem->minimumThreshold()->quantity()->amount());
        $entity->setThresholdUnit($stockItem->minimumThreshold()->quantity()->unit()->toString());
        $entity->setThresholdType($stockItem->minimumThreshold()->type()->value);
        $entity->setTrackStock($stockItem->trackStock());
        $entity->setStatus($stockItem->status()->value);
        $entity->setCreatedAt($stockItem->createdAt());
        $entity->setUpdatedAt($stockItem->updatedAt());

        // Clear and rebuild lots collection
        $entity->clearLots();

        foreach ($stockItem->lots() as $lot) {
            $lotEntity = $this->lotMapper->toEntity($lot, $entity);
            $entity->addLot($lotEntity);
        }

        return $entity;
    }

    /**
     * Reconstitutes domain StockItem from entity.
     * Reads totalOnHand DIRECTLY from entity — does NOT recompute from lot sums.
     */
    public function toDomain(StockItemEntity $entity): StockItem
    {
        $unit             = UnitOfMeasure::fromString($entity->getTotalOnHandUnit());
        $totalOnHand      = Quantity::of($entity->getTotalOnHandAmount(), $unit);
        $reservedQuantity = Quantity::of($entity->getReservedAmount(), $unit);

        $thresholdUnit = UnitOfMeasure::fromString($entity->getThresholdUnit());
        $threshold     = new StockThreshold(
            Quantity::of($entity->getThresholdAmount(), $thresholdUnit),
            StockThresholdType::from($entity->getThresholdType()),
        );

        $lots = [];
        foreach ($entity->getLots() as $lotEntity) {
            $lots[] = $this->lotMapper->toDomain($lotEntity);
        }

        return StockItem::reconstitute(
            id: StockItemId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            articleId: ArticleId::fromString($entity->getArticleId()->toString()),
            unit: $unit,
            totalOnHand: $totalOnHand,
            reservedQuantity: $reservedQuantity,
            lots: $lots,
            minimumThreshold: $threshold,
            trackStock: $entity->isTrackStock(),
            status: StockItemStatus::from($entity->getStatus()),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
