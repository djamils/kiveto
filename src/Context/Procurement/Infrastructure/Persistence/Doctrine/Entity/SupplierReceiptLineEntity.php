<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__supplier_receipt_lines.
 */
#[ORM\Entity]
#[ORM\Table]
class SupplierReceiptLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'purchase_order_line_id', type: UuidType::NAME)]
    private Uuid $purchaseOrderLineId;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME)]
    private Uuid $articleId;

    #[ORM\Column(name: 'received_amount', type: 'string', length: 32)]
    private string $receivedAmount;

    #[ORM\Column(name: 'received_unit', type: 'string', length: 32)]
    private string $receivedUnit;

    #[ORM\Column(name: 'lot_number', type: 'string', length: 64, nullable: true)]
    private ?string $lotNumber;

    #[ORM\Column(name: 'lot_expiry_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lotExpiryDate;

    #[ORM\Column(name: 'lot_manufactured_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lotManufacturedAt;

    #[ORM\Column(name: 'actual_unit_price_minor', type: 'integer', nullable: true)]
    private ?int $actualUnitPriceMinor;

    #[ORM\Column(name: 'actual_unit_price_currency', type: 'string', length: 3, nullable: true)]
    private ?string $actualUnitPriceCurrency;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\ManyToOne(targetEntity: SupplierReceiptEntity::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'supplier_receipt_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private SupplierReceiptEntity $supplierReceipt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getPurchaseOrderLineId(): Uuid
    {
        return $this->purchaseOrderLineId;
    }

    public function setPurchaseOrderLineId(Uuid $purchaseOrderLineId): void
    {
        $this->purchaseOrderLineId = $purchaseOrderLineId;
    }

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getReceivedAmount(): string
    {
        return $this->receivedAmount;
    }

    public function setReceivedAmount(string $receivedAmount): void
    {
        $this->receivedAmount = $receivedAmount;
    }

    public function getReceivedUnit(): string
    {
        return $this->receivedUnit;
    }

    public function setReceivedUnit(string $receivedUnit): void
    {
        $this->receivedUnit = $receivedUnit;
    }

    public function getLotNumber(): ?string
    {
        return $this->lotNumber;
    }

    public function setLotNumber(?string $lotNumber): void
    {
        $this->lotNumber = $lotNumber;
    }

    public function getLotExpiryDate(): ?\DateTimeImmutable
    {
        return $this->lotExpiryDate;
    }

    public function setLotExpiryDate(?\DateTimeImmutable $lotExpiryDate): void
    {
        $this->lotExpiryDate = $lotExpiryDate;
    }

    public function getLotManufacturedAt(): ?\DateTimeImmutable
    {
        return $this->lotManufacturedAt;
    }

    public function setLotManufacturedAt(?\DateTimeImmutable $lotManufacturedAt): void
    {
        $this->lotManufacturedAt = $lotManufacturedAt;
    }

    public function getActualUnitPriceMinor(): ?int
    {
        return $this->actualUnitPriceMinor;
    }

    public function setActualUnitPriceMinor(?int $actualUnitPriceMinor): void
    {
        $this->actualUnitPriceMinor = $actualUnitPriceMinor;
    }

    public function getActualUnitPriceCurrency(): ?string
    {
        return $this->actualUnitPriceCurrency;
    }

    public function setActualUnitPriceCurrency(?string $actualUnitPriceCurrency): void
    {
        $this->actualUnitPriceCurrency = $actualUnitPriceCurrency;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getSupplierReceipt(): SupplierReceiptEntity
    {
        return $this->supplierReceipt;
    }

    public function setSupplierReceipt(SupplierReceiptEntity $supplierReceipt): void
    {
        $this->supplierReceipt = $supplierReceipt;
    }
}
