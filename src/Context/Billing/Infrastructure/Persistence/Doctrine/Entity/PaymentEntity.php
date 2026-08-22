<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_payment_invoice', columns: ['invoice_id'])]
#[ORM\Index(name: 'idx_payment_clinic_received', columns: ['clinic_id', 'received_at'])]
class PaymentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'invoice_id', type: UuidType::NAME)]
    private Uuid $invoiceId;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 20, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(name: 'amount_minor_units', type: 'integer')]
    private int $amountMinorUnits;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(name: 'recorded_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $recordedByUserId = null;

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

    public function getInvoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(Uuid $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    public function getAmountMinorUnits(): int
    {
        return $this->amountMinorUnits;
    }

    public function setAmountMinorUnits(int $amountMinorUnits): void
    {
        $this->amountMinorUnits = $amountMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): void
    {
        $this->receivedAt = $receivedAt;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getRecordedByUserId(): ?Uuid
    {
        return $this->recordedByUserId;
    }

    public function setRecordedByUserId(?Uuid $recordedByUserId): void
    {
        $this->recordedByUserId = $recordedByUserId;
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
