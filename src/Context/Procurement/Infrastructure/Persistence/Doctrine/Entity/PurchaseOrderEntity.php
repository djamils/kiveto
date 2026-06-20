<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__purchase_orders.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_po_clinic_number', columns: ['clinic_id', 'order_number'])]
#[ORM\Index(name: 'idx_po_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_po_supplier', columns: ['supplier_id'])]
class PurchaseOrderEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'supplier_id', type: UuidType::NAME)]
    private Uuid $supplierId;

    #[ORM\Column(name: 'supplier_account_id', type: UuidType::NAME)]
    private Uuid $supplierAccountId;

    #[ORM\Column(name: 'order_number', type: 'string', length: 32)]
    private string $orderNumber;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status;

    #[ORM\Column(name: 'external_reference_value', type: 'string', length: 255, nullable: true)]
    private ?string $externalReferenceValue;

    #[ORM\Column(name: 'external_reference_provided_by', type: 'string', length: 128, nullable: true)]
    private ?string $externalReferenceProvidedBy;

    #[ORM\Column(name: 'delivery_address_json', type: 'text', nullable: true)]
    private ?string $deliveryAddressJson;

    #[ORM\Column(name: 'pdf_file_id', type: 'string', length: 255, nullable: true)]
    private ?string $pdfFileId;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt;

    #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt;

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
     * @var Collection<int, PurchaseOrderLineEntity>
     */
    #[ORM\OneToMany(
        targetEntity: PurchaseOrderLineEntity::class,
        mappedBy: 'purchaseOrder',
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

    public function getSupplierAccountId(): Uuid
    {
        return $this->supplierAccountId;
    }

    public function setSupplierAccountId(Uuid $supplierAccountId): void
    {
        $this->supplierAccountId = $supplierAccountId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getExternalReferenceValue(): ?string
    {
        return $this->externalReferenceValue;
    }

    public function setExternalReferenceValue(?string $externalReferenceValue): void
    {
        $this->externalReferenceValue = $externalReferenceValue;
    }

    public function getExternalReferenceProvidedBy(): ?string
    {
        return $this->externalReferenceProvidedBy;
    }

    public function setExternalReferenceProvidedBy(?string $externalReferenceProvidedBy): void
    {
        $this->externalReferenceProvidedBy = $externalReferenceProvidedBy;
    }

    public function getDeliveryAddressJson(): ?string
    {
        return $this->deliveryAddressJson;
    }

    public function setDeliveryAddressJson(?string $deliveryAddressJson): void
    {
        $this->deliveryAddressJson = $deliveryAddressJson;
    }

    public function getPdfFileId(): ?string
    {
        return $this->pdfFileId;
    }

    public function setPdfFileId(?string $pdfFileId): void
    {
        $this->pdfFileId = $pdfFileId;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
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

    /** @return Collection<int, PurchaseOrderLineEntity> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(PurchaseOrderLineEntity $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setPurchaseOrder($this);
        }
    }

    public function clearLines(): void
    {
        $this->lines->clear();
    }
}
