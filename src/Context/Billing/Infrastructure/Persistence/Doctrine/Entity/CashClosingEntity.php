<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One closed business day of a clinic's till.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_cash_closing_day', columns: ['clinic_id', 'business_date'])]
#[ORM\UniqueConstraint(name: 'uniq_cash_closing_number', columns: ['clinic_id', 'number'])]
class CashClosingEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'integer')]
    private int $number = 0;

    #[ORM\Column(name: 'business_date', type: 'string', length: 10)]
    private string $businessDate;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    private array $expected = [];

    #[ORM\Column(name: 'counted_cash_minor_units', type: 'integer', nullable: true)]
    private ?int $countedCashMinorUnits = null;

    #[ORM\Column(name: 'counted_cheques_minor_units', type: 'integer', nullable: true)]
    private ?int $countedChequesMinorUnits = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'closed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $closedAt;

    #[ORM\Column(name: 'closed_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $closedByUserId = null;

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

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getBusinessDate(): string
    {
        return $this->businessDate;
    }

    public function setBusinessDate(string $businessDate): void
    {
        $this->businessDate = $businessDate;
    }

    /**
     * @return array<string, int>
     */
    public function getExpected(): array
    {
        return $this->expected;
    }

    /**
     * @param array<string, int> $expected
     */
    public function setExpected(array $expected): void
    {
        $this->expected = $expected;
    }

    public function getCountedCashMinorUnits(): ?int
    {
        return $this->countedCashMinorUnits;
    }

    public function setCountedCashMinorUnits(?int $countedCashMinorUnits): void
    {
        $this->countedCashMinorUnits = $countedCashMinorUnits;
    }

    public function getCountedChequesMinorUnits(): ?int
    {
        return $this->countedChequesMinorUnits;
    }

    public function setCountedChequesMinorUnits(?int $countedChequesMinorUnits): void
    {
        $this->countedChequesMinorUnits = $countedChequesMinorUnits;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getClosedAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(\DateTimeImmutable $closedAt): void
    {
        $this->closedAt = $closedAt;
    }

    public function getClosedByUserId(): ?Uuid
    {
        return $this->closedByUserId;
    }

    public function setClosedByUserId(?Uuid $closedByUserId): void
    {
        $this->closedByUserId = $closedByUserId;
    }
}
