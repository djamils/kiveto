<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_act_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_act_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_act_code_tenant', columns: ['code', 'tenant_id'])]
class ActEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'tenant_id', type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(type: 'string', length: 150)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20)]
    private string $code;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $category;

    #[ORM\Column(name: 'tax_category_code', type: 'string', length: 50)]
    private string $taxCategoryCode;

    #[ORM\Column(name: 'base_price_minor_units', type: 'integer')]
    private int $basePriceMinorUnits;

    #[ORM\Column(name: 'base_price_currency', type: 'string', length: 3)]
    private string $basePriceCurrency;

    #[ORM\Column(name: 'estimated_duration_minutes', type: 'integer')]
    private int $estimatedDurationMinutes;

    #[ORM\Column(name: 'requires_anesthesia', type: 'boolean')]
    private bool $requiresAnesthesia;

    #[ORM\Column(type: 'string', length: 20, enumType: ActStatus::class)]
    private ActStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function setTenantId(Uuid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function getTaxCategoryCode(): string
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(string $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getBasePriceMinorUnits(): int
    {
        return $this->basePriceMinorUnits;
    }

    public function setBasePriceMinorUnits(int $basePriceMinorUnits): void
    {
        $this->basePriceMinorUnits = $basePriceMinorUnits;
    }

    public function getBasePriceCurrency(): string
    {
        return $this->basePriceCurrency;
    }

    public function setBasePriceCurrency(string $basePriceCurrency): void
    {
        $this->basePriceCurrency = $basePriceCurrency;
    }

    public function getEstimatedDurationMinutes(): int
    {
        return $this->estimatedDurationMinutes;
    }

    public function setEstimatedDurationMinutes(int $estimatedDurationMinutes): void
    {
        $this->estimatedDurationMinutes = $estimatedDurationMinutes;
    }

    public function getRequiresAnesthesia(): bool
    {
        return $this->requiresAnesthesia;
    }

    public function setRequiresAnesthesia(bool $requiresAnesthesia): void
    {
        $this->requiresAnesthesia = $requiresAnesthesia;
    }

    public function getStatus(): ActStatus
    {
        return $this->status;
    }

    public function setStatus(ActStatus $status): void
    {
        $this->status = $status;
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
}
