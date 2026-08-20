<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_prescription_line', columns: ['consultation_id', 'position'])]
class PrescriptionLineEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'binary', length: 16, nullable: true)]
    private ?string $articleId = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $dose = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $frequency = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $durationDays = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(type: 'integer')]
    private int $unitPriceMinorUnits;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $taxCategoryCode = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $createdByUserId;

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

    public function getArticleId(): ?string
    {
        return $this->articleId;
    }

    public function setArticleId(?string $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDose(): ?string
    {
        return $this->dose;
    }

    public function setDose(?string $dose): void
    {
        $this->dose = $dose;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(?string $frequency): void
    {
        $this->frequency = $frequency;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): void
    {
        $this->durationDays = $durationDays;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): void
    {
        $this->route = $route;
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

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function setCreatedAtUtc(\DateTimeImmutable $createdAtUtc): void
    {
        $this->createdAtUtc = $createdAtUtc;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    public function setCreatedByUserId(string $createdByUserId): void
    {
        $this->createdByUserId = $createdByUserId;
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
