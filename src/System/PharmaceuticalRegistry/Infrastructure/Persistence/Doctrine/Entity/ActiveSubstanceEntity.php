<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_pharma_active_substance_label', columns: ['label_normalized'])]
class ActiveSubstanceEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'label', type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(name: 'label_normalized', type: 'string', length: 255)]
    private string $labelNormalized;

    #[ORM\Column(name: 'inn_code', type: 'string', length: 64, nullable: true)]
    private ?string $innCode;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLabelNormalized(): string
    {
        return $this->labelNormalized;
    }

    public function setLabelNormalized(string $labelNormalized): void
    {
        $this->labelNormalized = $labelNormalized;
    }

    public function getInnCode(): ?string
    {
        return $this->innCode;
    }

    public function setInnCode(?string $innCode): void
    {
        $this->innCode = $innCode;
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
