<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockMovement\Event\StockMovementRecorded;
use App\Context\Inventory\Domain\StockMovement\Exception\IncoherentMovementTypeReasonException;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class StockMovement extends AggregateRoot
{
    private function __construct(
        private readonly StockMovementId $id,
        private readonly ClinicId $clinicId,
        private readonly ArticleId $articleId,
        private readonly ?LotId $lotId,
        private readonly MovementType $type,
        private readonly MovementReason $reason,
        private readonly Quantity $quantity,
        private readonly \DateTimeImmutable $occurredAt,
        private readonly ?string $reference,
        private readonly ?string $performedBy,
        private readonly ?string $note,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        StockMovementId $id,
        ClinicId $clinicId,
        ArticleId $articleId,
        ?LotId $lotId,
        MovementType $type,
        MovementReason $reason,
        Quantity $quantity,
        \DateTimeImmutable $occurredAt,
        ?string $reference,
        ?string $performedBy,
        ?string $note,
        \DateTimeImmutable $createdAt,
    ): self {
        self::assertTypeReasonCoherence($type, $reason);

        if ($quantity->isZero()) {
            throw new \InvalidArgumentException('StockMovement quantity must be greater than zero.');
        }

        $movement = new self(
            id: $id,
            clinicId: $clinicId,
            articleId: $articleId,
            lotId: $lotId,
            type: $type,
            reason: $reason,
            quantity: $quantity,
            occurredAt: $occurredAt,
            reference: $reference,
            performedBy: $performedBy,
            note: $note,
            createdAt: $createdAt,
        );

        $movement->recordDomainEvent(new StockMovementRecorded(
            movementId: $id->toString(),
            clinicId: $clinicId->toString(),
            articleId: $articleId->toString(),
            lotId: $lotId?->toString(),
            type: $type->value,
            reason: $reason->value,
            quantityAmount: $quantity->amount(),
            quantityUnit: $quantity->unit()->toString(),
            occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
            reference: $reference,
            performedBy: $performedBy,
            note: $note,
        ));

        return $movement;
    }

    /**
     * Reconstitutes a StockMovement from persisted data. No events recorded.
     */
    public static function reconstitute(
        StockMovementId $id,
        ClinicId $clinicId,
        ArticleId $articleId,
        ?LotId $lotId,
        MovementType $type,
        MovementReason $reason,
        Quantity $quantity,
        \DateTimeImmutable $occurredAt,
        ?string $reference,
        ?string $performedBy,
        ?string $note,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            articleId: $articleId,
            lotId: $lotId,
            type: $type,
            reason: $reason,
            quantity: $quantity,
            occurredAt: $occurredAt,
            reference: $reference,
            performedBy: $performedBy,
            note: $note,
            createdAt: $createdAt,
        );
    }

    // -------------------------------------------------------------------------
    // Getters (immutable aggregate — no mutators)
    // -------------------------------------------------------------------------

    public function id(): StockMovementId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function articleId(): ArticleId
    {
        return $this->articleId;
    }

    public function lotId(): ?LotId
    {
        return $this->lotId;
    }

    public function type(): MovementType
    {
        return $this->type;
    }

    public function reason(): MovementReason
    {
        return $this->reason;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function performedBy(): ?string
    {
        return $this->performedBy;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function assertTypeReasonCoherence(MovementType $type, MovementReason $reason): void
    {
        $expectedType = match ($reason) {
            MovementReason::RECEIPT_SUPPLIER,
            MovementReason::RECEIPT_OPENING,
            MovementReason::RECEIPT_RETURN_FROM_CLIENT => MovementType::IN,

            MovementReason::CONSUMPTION_CONSULTATION,
            MovementReason::SALE_RETAIL,
            MovementReason::LOSS_EXPIRY,
            MovementReason::LOSS_BREAKAGE,
            MovementReason::LOSS_THEFT,
            MovementReason::RECALL_RETURN_TO_SUPPLIER => MovementType::OUT,

            MovementReason::PHYSICAL_INVENTORY,
            MovementReason::CORRECTION => MovementType::ADJUSTMENT,
        };

        if ($expectedType !== $type) {
            throw new IncoherentMovementTypeReasonException($type, $reason);
        }
    }
}
