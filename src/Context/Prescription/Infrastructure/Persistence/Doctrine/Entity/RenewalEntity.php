<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_renewal_prescription', columns: ['prescription_id'])]
class RenewalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'prescription_id', type: UuidType::NAME)]
    private Uuid $prescriptionId;

    #[ORM\Column(name: 'renewed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $renewedAt;

    #[ORM\Column(name: 'new_valid_until', type: 'datetime_immutable')]
    private \DateTimeImmutable $newValidUntil;

    #[ORM\Column(name: 'renewed_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $renewedByUserId = null;

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

    public function getPrescriptionId(): Uuid
    {
        return $this->prescriptionId;
    }

    public function setPrescriptionId(Uuid $prescriptionId): void
    {
        $this->prescriptionId = $prescriptionId;
    }

    public function getRenewedAt(): \DateTimeImmutable
    {
        return $this->renewedAt;
    }

    public function setRenewedAt(\DateTimeImmutable $renewedAt): void
    {
        $this->renewedAt = $renewedAt;
    }

    public function getNewValidUntil(): \DateTimeImmutable
    {
        return $this->newValidUntil;
    }

    public function setNewValidUntil(\DateTimeImmutable $newValidUntil): void
    {
        $this->newValidUntil = $newValidUntil;
    }

    public function getRenewedByUserId(): ?Uuid
    {
        return $this->renewedByUserId;
    }

    public function setRenewedByUserId(?Uuid $renewedByUserId): void
    {
        $this->renewedByUserId = $renewedByUserId;
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
