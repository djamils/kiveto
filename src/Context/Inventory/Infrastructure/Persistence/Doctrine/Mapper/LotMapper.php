<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Inventory\Domain\Shared\ValueObject\SupplierId;
use App\Context\Inventory\Domain\StockItem\Entity\Lot;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\LotEntity;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Uid\Uuid;

final readonly class LotMapper
{
    public function toEntity(Lot $lot, StockItemEntity $stockItemEntity): LotEntity
    {
        $entity = new LotEntity();

        $entity->setId(Uuid::fromString($lot->id()->toString()));
        $entity->setStockItem($stockItemEntity);
        $entity->setLotNumber($lot->number()->toString());
        $entity->setQuantityAmount($lot->quantity()->amount());
        $entity->setQuantityUnit($lot->quantity()->unit()->toString());
        $entity->setExpiryDate($lot->expiryDate());
        $entity->setReceivedAt($lot->receivedAt());
        $entity->setSourceSupplier(null !== $lot->sourceSupplier() ? Uuid::fromString($lot->sourceSupplier()->toString()) : null);
        $entity->setStatus($lot->status()->value);
        $entity->setCreatedAt($lot->createdAt());
        $entity->setUpdatedAt($lot->updatedAt());

        return $entity;
    }

    public function toDomain(LotEntity $entity): Lot
    {
        $unit     = UnitOfMeasure::fromString($entity->getQuantityUnit());
        $quantity = Quantity::of($entity->getQuantityAmount(), $unit);

        $supplierId = null !== $entity->getSourceSupplier()
            ? SupplierId::fromString($entity->getSourceSupplier()->toString())
            : null;

        return new Lot(
            id: LotId::fromString($entity->getId()->toString()),
            number: LotNumber::fromString($entity->getLotNumber()),
            quantity: $quantity,
            expiryDate: $entity->getExpiryDate(),
            receivedAt: $entity->getReceivedAt(),
            sourceSupplier: $supplierId,
            status: LotStatus::from($entity->getStatus()),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
