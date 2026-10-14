<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_invoice_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_invoice_clinic_issued', columns: ['clinic_id', 'issued_at'])]
#[ORM\Index(name: 'idx_invoice_admission', columns: ['admission_id'])]
#[ORM\Index(name: 'idx_invoice_payer', columns: ['clinic_id', 'payer_client_id'])]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', columns: ['clinic_id', 'number'])]
class InvoiceEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', length: 20, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status;

    #[ORM\Column(name: 'admission_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $admissionId = null;

    #[ORM\Column(name: 'payer_client_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $payerClientId = null;

    #[ORM\Column(name: 'payer_name', type: 'string', length: 255)]
    private string $payerName;

    /** @var list<string> */
    #[ORM\Column(name: 'payer_address_lines', type: 'json')]
    private array $payerAddressLines = [];

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'discount_kind', type: 'string', length: 16, nullable: true, enumType: DiscountKind::class)]
    private ?DiscountKind $discountKind = null;

    #[ORM\Column(name: 'discount_value', type: 'string', length: 20, nullable: true)]
    private ?string $discountValue = null;

    #[ORM\Column(name: 'discount_reason', type: 'string', length: 255, nullable: true)]
    private ?string $discountReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'issued_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'paid_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'cancellation_reason', type: 'string', length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(name: 'credited_minor_units', type: 'integer', options: ['default' => 0])]
    private int $creditedMinorUnits = 0;

    #[ORM\Column(name: 'refunded_minor_units', type: 'integer', options: ['default' => 0])]
    private int $refundedMinorUnits = 0;

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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): void
    {
        $this->status = $status;
    }

    public function getAdmissionId(): ?Uuid
    {
        return $this->admissionId;
    }

    public function setAdmissionId(?Uuid $admissionId): void
    {
        $this->admissionId = $admissionId;
    }

    public function getPayerClientId(): ?Uuid
    {
        return $this->payerClientId;
    }

    public function setPayerClientId(?Uuid $payerClientId): void
    {
        $this->payerClientId = $payerClientId;
    }

    public function getPayerName(): string
    {
        return $this->payerName;
    }

    public function setPayerName(string $payerName): void
    {
        $this->payerName = $payerName;
    }

    /**
     * @return list<string>
     */
    public function getPayerAddressLines(): array
    {
        return $this->payerAddressLines;
    }

    /**
     * @param list<string> $payerAddressLines
     */
    public function setPayerAddressLines(array $payerAddressLines): void
    {
        $this->payerAddressLines = $payerAddressLines;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getDiscountKind(): ?DiscountKind
    {
        return $this->discountKind;
    }

    public function setDiscountKind(?DiscountKind $discountKind): void
    {
        $this->discountKind = $discountKind;
    }

    public function getDiscountValue(): ?string
    {
        return $this->discountValue;
    }

    public function setDiscountValue(?string $discountValue): void
    {
        $this->discountValue = $discountValue;
    }

    public function getDiscountReason(): ?string
    {
        return $this->discountReason;
    }

    public function setDiscountReason(?string $discountReason): void
    {
        $this->discountReason = $discountReason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
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

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): void
    {
        $this->issuedAt = $issuedAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): void
    {
        $this->cancellationReason = $cancellationReason;
    }

    public function getCreditedMinorUnits(): int
    {
        return $this->creditedMinorUnits;
    }

    public function setCreditedMinorUnits(int $creditedMinorUnits): void
    {
        $this->creditedMinorUnits = $creditedMinorUnits;
    }

    public function getRefundedMinorUnits(): int
    {
        return $this->refundedMinorUnits;
    }

    public function setRefundedMinorUnits(int $refundedMinorUnits): void
    {
        $this->refundedMinorUnits = $refundedMinorUnits;
    }
}
