<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: inventory__stock_alerts.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_alert_clinic_resolved', columns: ['clinic_id', 'resolved_at'])]
#[ORM\Index(name: 'idx_alert_severity', columns: ['severity'])]
#[ORM\Index(name: 'idx_alert_stock_item_type', columns: ['stock_item_id', 'type'])]
class StockAlertEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\ManyToOne(targetEntity: StockItemEntity::class)]
    #[ORM\JoinColumn(name: 'stock_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private StockItemEntity $stockItem;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME)]
    private Uuid $articleId;

    #[ORM\Column(name: 'article_name', type: 'string', length: 255)]
    private string $articleName;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(type: 'string', length: 16)]
    private string $severity;

    #[ORM\Column(name: 'current_level_amount', type: 'string', length: 32, nullable: true)]
    private ?string $currentLevelAmount;

    #[ORM\Column(name: 'current_level_unit', type: 'string', length: 16, nullable: true)]
    private ?string $currentLevelUnit;

    #[ORM\Column(name: 'earliest_expiry', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $earliestExpiry;

    #[ORM\Column(name: 'detected_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $detectedAt;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt;

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

    public function getStockItem(): StockItemEntity
    {
        return $this->stockItem;
    }

    public function setStockItem(StockItemEntity $stockItem): void
    {
        $this->stockItem = $stockItem;
    }

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getArticleName(): string
    {
        return $this->articleName;
    }

    public function setArticleName(string $articleName): void
    {
        $this->articleName = $articleName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): void
    {
        $this->severity = $severity;
    }

    public function getCurrentLevelAmount(): ?string
    {
        return $this->currentLevelAmount;
    }

    public function setCurrentLevelAmount(?string $currentLevelAmount): void
    {
        $this->currentLevelAmount = $currentLevelAmount;
    }

    public function getCurrentLevelUnit(): ?string
    {
        return $this->currentLevelUnit;
    }

    public function setCurrentLevelUnit(?string $currentLevelUnit): void
    {
        $this->currentLevelUnit = $currentLevelUnit;
    }

    public function getEarliestExpiry(): ?\DateTimeImmutable
    {
        return $this->earliestExpiry;
    }

    public function setEarliestExpiry(?\DateTimeImmutable $earliestExpiry): void
    {
        $this->earliestExpiry = $earliestExpiry;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTimeImmutable $detectedAt): void
    {
        $this->detectedAt = $detectedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): void
    {
        $this->resolvedAt = $resolvedAt;
    }
}
