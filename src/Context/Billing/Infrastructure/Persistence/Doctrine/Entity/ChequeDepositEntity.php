<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One remittance of cheques to the bank.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_cheque_deposit_clinic', columns: ['clinic_id', 'deposited_at'])]
class ChequeDepositEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'total_minor_units', type: 'integer')]
    private int $totalMinorUnits = 0;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'deposited_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $depositedAt;

    #[ORM\Column(name: 'deposited_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $depositedByUserId = null;

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

    public function getTotalMinorUnits(): int
    {
        return $this->totalMinorUnits;
    }

    public function setTotalMinorUnits(int $totalMinorUnits): void
    {
        $this->totalMinorUnits = $totalMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getDepositedAt(): \DateTimeImmutable
    {
        return $this->depositedAt;
    }

    public function setDepositedAt(\DateTimeImmutable $depositedAt): void
    {
        $this->depositedAt = $depositedAt;
    }

    public function getDepositedByUserId(): ?Uuid
    {
        return $this->depositedByUserId;
    }

    public function setDepositedByUserId(?Uuid $depositedByUserId): void
    {
        $this->depositedByUserId = $depositedByUserId;
    }
}
