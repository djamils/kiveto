<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: inventory__stock_movements.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_sm_clinic_article', columns: ['clinic_id', 'article_id'])]
#[ORM\Index(name: 'idx_sm_clinic_occurred', columns: ['clinic_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_sm_lot', columns: ['lot_id'])]
#[ORM\Index(name: 'idx_sm_reason', columns: ['reason'])]
#[ORM\Index(name: 'idx_sm_reference', columns: ['reference'])]
class StockMovementEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME)]
    private Uuid $articleId;

    #[ORM\Column(name: 'lot_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $lotId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(type: 'string', length: 64)]
    private string $reason;

    #[ORM\Column(name: 'quantity_amount', type: 'string', length: 32)]
    private string $quantityAmount;

    #[ORM\Column(name: 'quantity_unit', type: 'string', length: 16)]
    private string $quantityUnit;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $reference;

    #[ORM\Column(name: 'performed_by', type: UuidType::NAME, nullable: true)]
    private ?Uuid $performedBy;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getLotId(): ?Uuid
    {
        return $this->lotId;
    }

    public function setLotId(?Uuid $lotId): void
    {
        $this->lotId = $lotId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getQuantityAmount(): string
    {
        return $this->quantityAmount;
    }

    public function setQuantityAmount(string $quantityAmount): void
    {
        $this->quantityAmount = $quantityAmount;
    }

    public function getQuantityUnit(): string
    {
        return $this->quantityUnit;
    }

    public function setQuantityUnit(string $quantityUnit): void
    {
        $this->quantityUnit = $quantityUnit;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getPerformedBy(): ?Uuid
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?Uuid $performedBy): void
    {
        $this->performedBy = $performedBy;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
