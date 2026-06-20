<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__supplier_receipts.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_sr_supplier_dnr', columns: ['supplier_id', 'delivery_note_reference'])]
#[ORM\Index(name: 'idx_sr_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_sr_po', columns: ['purchase_order_id'])]
class SupplierReceiptEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'supplier_id', type: UuidType::NAME)]
    private Uuid $supplierId;

    #[ORM\Column(name: 'purchase_order_id', type: UuidType::NAME)]
    private Uuid $purchaseOrderId;

    #[ORM\Column(name: 'delivery_note_reference', type: 'string', length: 128)]
    private string $deliveryNoteReference;

    #[ORM\Column(name: 'match_type', type: 'string', length: 32)]
    private string $matchType;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

    #[ORM\Column(name: 'validated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validatedAt;

    #[ORM\Column(name: 'received_by', type: UuidType::NAME, nullable: true)]
    private ?Uuid $receivedBy;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * EAGER fetch — lines are always needed when reconstituting the aggregate.
     *
     * @var Collection<int, SupplierReceiptLineEntity>
     */
    #[ORM\OneToMany(
        targetEntity: SupplierReceiptLineEntity::class,
        mappedBy: 'supplierReceipt',
        fetch: 'EAGER',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

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

    public function getSupplierId(): Uuid
    {
        return $this->supplierId;
    }

    public function setSupplierId(Uuid $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getPurchaseOrderId(): Uuid
    {
        return $this->purchaseOrderId;
    }

    public function setPurchaseOrderId(Uuid $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
    }

    public function getDeliveryNoteReference(): string
    {
        return $this->deliveryNoteReference;
    }

    public function setDeliveryNoteReference(string $deliveryNoteReference): void
    {
        $this->deliveryNoteReference = $deliveryNoteReference;
    }

    public function getMatchType(): string
    {
        return $this->matchType;
    }

    public function setMatchType(string $matchType): void
    {
        $this->matchType = $matchType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): void
    {
        $this->validatedAt = $validatedAt;
    }

    public function getReceivedBy(): ?Uuid
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?Uuid $receivedBy): void
    {
        $this->receivedBy = $receivedBy;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /** @return Collection<int, SupplierReceiptLineEntity> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(SupplierReceiptLineEntity $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setSupplierReceipt($this);
        }
    }

    public function clearLines(): void
    {
        $this->lines->clear();
    }
}
