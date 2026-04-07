<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_consultation_performed', columns: ['consultation_id', 'performed_at_utc'])]
class PerformedActEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'binary', length: 16)]
    private string $id;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $consultationId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $performedAtUtc;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAtUtc;

    #[ORM\Column(type: 'binary', length: 16)]
    private string $createdByUserId;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPerformedAtUtc(): \DateTimeImmutable
    {
        return $this->performedAtUtc;
    }

    public function setPerformedAtUtc(\DateTimeImmutable $performedAtUtc): void
    {
        $this->performedAtUtc = $performedAtUtc;
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
}
