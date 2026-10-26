<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_invoice_line_invoice', columns: ['invoice_id', 'position'])]
class InvoiceLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'invoice_id', type: UuidType::NAME)]
    private Uuid $invoiceId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(name: 'unit_price_minor_units', type: 'integer')]
    private int $unitPriceMinorUnits;

    #[ORM\Column(name: 'unit_price_mode', type: 'string', length: 4)]
    private string $unitPriceMode = 'excl';

    #[ORM\Column(name: 'unit_net_micro_units', type: 'bigint')]
    private int $unitNetMicroUnits = 0;

    #[ORM\Column(name: 'operation_category', type: 'string', length: 16, nullable: true)]
    private ?string $operationCategory = null;

    #[ORM\Column(name: 'net_minor_units', type: 'integer')]
    private int $netMinorUnits;

    #[ORM\Column(name: 'gross_minor_units', type: 'integer')]
    private int $grossMinorUnits;

    #[ORM\Column(name: 'tax_rate_percent', type: 'decimal', precision: 7, scale: 4)]
    private string $taxRatePercent;

    #[ORM\Column(name: 'tax_category_code', type: 'string', length: 40, nullable: true)]
    private ?string $taxCategoryCode = null;

    #[ORM\Column(type: 'string', length: 20, enumType: InvoiceLineSource::class)]
    private InvoiceLineSource $source;

    #[ORM\Column(name: 'source_reference', type: 'string', length: 64, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column(type: 'smallint')]
    private int $position = 0;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getInvoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(Uuid $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPriceMinorUnits(): int
    {
        return $this->unitPriceMinorUnits;
    }

    public function setUnitPriceMinorUnits(int $unitPriceMinorUnits): void
    {
        $this->unitPriceMinorUnits = $unitPriceMinorUnits;
    }

    public function getNetMinorUnits(): int
    {
        return $this->netMinorUnits;
    }

    public function setNetMinorUnits(int $netMinorUnits): void
    {
        $this->netMinorUnits = $netMinorUnits;
    }

    public function getGrossMinorUnits(): int
    {
        return $this->grossMinorUnits;
    }

    public function setGrossMinorUnits(int $grossMinorUnits): void
    {
        $this->grossMinorUnits = $grossMinorUnits;
    }

    public function getTaxRatePercent(): string
    {
        return $this->taxRatePercent;
    }

    public function setTaxRatePercent(string $taxRatePercent): void
    {
        $this->taxRatePercent = $taxRatePercent;
    }

    public function getTaxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(?string $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getSource(): InvoiceLineSource
    {
        return $this->source;
    }

    public function setSource(InvoiceLineSource $source): void
    {
        $this->source = $source;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?string $sourceReference): void
    {
        $this->sourceReference = $sourceReference;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getUnitPriceMode(): string
    {
        return $this->unitPriceMode;
    }

    public function setUnitPriceMode(string $unitPriceMode): void
    {
        $this->unitPriceMode = $unitPriceMode;
    }

    public function getUnitNetMicroUnits(): int
    {
        return $this->unitNetMicroUnits;
    }

    public function setUnitNetMicroUnits(int $unitNetMicroUnits): void
    {
        $this->unitNetMicroUnits = $unitNetMicroUnits;
    }

    public function getOperationCategory(): ?string
    {
        return $this->operationCategory;
    }

    public function setOperationCategory(?string $operationCategory): void
    {
        $this->operationCategory = $operationCategory;
    }
}
