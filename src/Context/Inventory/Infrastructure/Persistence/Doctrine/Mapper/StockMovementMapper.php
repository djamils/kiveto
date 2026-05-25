<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockMovementEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Uid\Uuid;

final readonly class StockMovementMapper
{
    public function toEntity(StockMovement $movement): StockMovementEntity
    {
        $entity = new StockMovementEntity();

        $entity->setId(Uuid::fromString($movement->id()->toString()));
        $entity->setClinicId(Uuid::fromString($movement->clinicId()->toString()));
        $entity->setArticleId(Uuid::fromString($movement->articleId()->toString()));
        $entity->setLotId(null !== $movement->lotId() ? Uuid::fromString($movement->lotId()->toString()) : null);
        $entity->setType($movement->type()->value);
        $entity->setReason($movement->reason()->value);
        $entity->setQuantityAmount($movement->quantity()->amount());
        $entity->setQuantityUnit($movement->quantity()->unit()->toString());
        $entity->setOccurredAt($movement->occurredAt());
        $entity->setReference($movement->reference());
        $entity->setPerformedBy(null !== $movement->performedBy() ? Uuid::fromString($movement->performedBy()) : null);
        $entity->setNote($movement->note());
        $entity->setCreatedAt($movement->createdAt());

        return $entity;
    }

    public function toDomain(StockMovementEntity $entity): StockMovement
    {
        $unit     = UnitOfMeasure::fromString($entity->getQuantityUnit());
        $quantity = Quantity::of($entity->getQuantityAmount(), $unit);

        $lotId = null !== $entity->getLotId()
            ? LotId::fromString($entity->getLotId()->toString())
            : null;

        return StockMovement::reconstitute(
            id: StockMovementId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            articleId: ArticleId::fromString($entity->getArticleId()->toString()),
            lotId: $lotId,
            type: MovementType::from($entity->getType()),
            reason: MovementReason::from($entity->getReason()),
            quantity: $quantity,
            occurredAt: $entity->getOccurredAt(),
            reference: $entity->getReference(),
            performedBy: $entity->getPerformedBy()?->toString(),
            note: $entity->getNote(),
            createdAt: $entity->getCreatedAt(),
        );
    }
}
