<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_billing_line', columns: ['consultation_id', 'position'])]
class BillingLineEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $sourceLineId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceMinorUnits;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $taxCategoryCode = null;

    #[ORM\Column(type: 'smallint')]
    private int $position = 0;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getConsultationId(): string
    {
        return $this->consultationId;
    }

    public function setConsultationId(string $consultationId): void
    {
        $this->consultationId = $consultationId;
    }

    public function getSourceLineId(): string
    {
        return $this->sourceLineId;
    }

    public function setSourceLineId(string $sourceLineId): void
    {
        $this->sourceLineId = $sourceLineId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getTaxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(?string $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
