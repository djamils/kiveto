<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_plan_action', columns: ['consultation_id', 'position'])]
class PlanActionEntity implements ConsultationChildEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'string', length: 30)]
    private string $kind;

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $catalogCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $posology = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $durationDays = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $followUpDays = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $unitPriceMinorUnits = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency = null;

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

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCatalogCode(): ?string
    {
        return $this->catalogCode;
    }

    public function setCatalogCode(?string $catalogCode): void
    {
        $this->catalogCode = $catalogCode;
    }

    public function getPosology(): ?string
    {
        return $this->posology;
    }

    public function setPosology(?string $posology): void
    {
        $this->posology = $posology;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): void
    {
        $this->durationDays = $durationDays;
    }

    public function getFollowUpDays(): ?int
    {
        return $this->followUpDays;
    }

    public function setFollowUpDays(?int $followUpDays): void
    {
        $this->followUpDays = $followUpDays;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPriceMinorUnits(): ?int
    {
        return $this->unitPriceMinorUnits;
    }

    public function setUnitPriceMinorUnits(?int $unitPriceMinorUnits): void
    {
        $this->unitPriceMinorUnits = $unitPriceMinorUnits;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
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
