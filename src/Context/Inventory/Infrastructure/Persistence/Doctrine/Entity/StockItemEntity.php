<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: inventory__stock_items.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_si_clinic_article', columns: ['clinic_id', 'article_id'])]
#[ORM\Index(name: 'idx_si_clinic_status', columns: ['clinic_id', 'status'])]
class StockItemEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME)]
    private Uuid $articleId;

    #[ORM\Column(name: 'total_on_hand_amount', type: 'string', length: 32)]
    private string $totalOnHandAmount;

    #[ORM\Column(name: 'total_on_hand_unit', type: 'string', length: 16)]
    private string $totalOnHandUnit;

    #[ORM\Column(name: 'reserved_amount', type: 'string', length: 32, options: ['default' => '0.000000'])]
    private string $reservedAmount;

    #[ORM\Column(name: 'threshold_amount', type: 'string', length: 32)]
    private string $thresholdAmount;

    #[ORM\Column(name: 'threshold_unit', type: 'string', length: 16)]
    private string $thresholdUnit;

    #[ORM\Column(name: 'threshold_type', type: 'string', length: 16)]
    private string $thresholdType;

    #[ORM\Column(name: 'track_stock', type: 'boolean', options: ['default' => true])]
    private bool $trackStock;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'ACTIVE'])]
    private string $status;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * EAGER fetch — justified by Messenger lifecycle (avoids EntityManagerClosed on lazy collection access).
     * StockItems have a small, bounded number of lots.
     *
     * @var Collection<int, LotEntity>
     */
    #[ORM\OneToMany(
        targetEntity: LotEntity::class,
        mappedBy: 'stockItem',
        fetch: 'EAGER',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $lots;

    public function __construct()
    {
        $this->lots = new ArrayCollection();
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

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getTotalOnHandAmount(): string
    {
        return $this->totalOnHandAmount;
    }

    public function setTotalOnHandAmount(string $totalOnHandAmount): void
    {
        $this->totalOnHandAmount = $totalOnHandAmount;
    }

    public function getTotalOnHandUnit(): string
    {
        return $this->totalOnHandUnit;
    }

    public function setTotalOnHandUnit(string $totalOnHandUnit): void
    {
        $this->totalOnHandUnit = $totalOnHandUnit;
    }

    public function getReservedAmount(): string
    {
        return $this->reservedAmount;
    }

    public function setReservedAmount(string $reservedAmount): void
    {
        $this->reservedAmount = $reservedAmount;
    }

    public function getThresholdAmount(): string
    {
        return $this->thresholdAmount;
    }

    public function setThresholdAmount(string $thresholdAmount): void
    {
        $this->thresholdAmount = $thresholdAmount;
    }

    public function getThresholdUnit(): string
    {
        return $this->thresholdUnit;
    }

    public function setThresholdUnit(string $thresholdUnit): void
    {
        $this->thresholdUnit = $thresholdUnit;
    }

    public function getThresholdType(): string
    {
        return $this->thresholdType;
    }

    public function setThresholdType(string $thresholdType): void
    {
        $this->thresholdType = $thresholdType;
    }

    public function isTrackStock(): bool
    {
        return $this->trackStock;
    }

    public function setTrackStock(bool $trackStock): void
    {
        $this->trackStock = $trackStock;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
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

    /** @return Collection<int, LotEntity> */
    public function getLots(): Collection
    {
        return $this->lots;
    }

    public function addLot(LotEntity $lot): void
    {
        if (!$this->lots->contains($lot)) {
            $this->lots->add($lot);
            $lot->setStockItem($this);
        }
    }

    public function removeLot(LotEntity $lot): void
    {
        $this->lots->removeElement($lot);
    }

    public function clearLots(): void
    {
        $this->lots->clear();
    }
}
