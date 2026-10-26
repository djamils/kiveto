<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Domain\ValueObject\CounterSalePayer;
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
    use PayerFiscalColumns;

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

    #[ORM\Column(name: 'payer_attention', type: 'string', length: 255, nullable: true)]
    private ?string $payerAttention = null;

    /** @var list<string> */
    #[ORM\Column(name: 'payer_address_lines', type: 'json')]
    private array $payerAddressLines = [];

    /** @var list<array{rate: string, lineTotal: int, allowance: int, vat: int}> */
    #[ORM\Column(name: 'tax_breakdown', type: 'json')]
    private array $taxBreakdown = [];

    #[ORM\Column(name: 'total_net_before_allowance', type: 'integer', options: ['default' => 0])]
    private int $totalNetBeforeAllowance = 0;

    #[ORM\Column(name: 'total_allowance', type: 'integer', options: ['default' => 0])]
    private int $totalAllowance = 0;

    #[ORM\Column(name: 'total_tax_basis', type: 'integer', options: ['default' => 0])]
    private int $totalTaxBasis = 0;

    #[ORM\Column(name: 'total_tax', type: 'integer', options: ['default' => 0])]
    private int $totalTax = 0;

    #[ORM\Column(name: 'total_gross', type: 'integer', options: ['default' => 0])]
    private int $totalGross = 0;

    #[ORM\Column(name: 'processing_rule', type: 'string', length: 3, nullable: true)]
    private ?string $processingRule = null;

    #[ORM\Column(name: 'invoicing_framework', type: 'string', length: 2, nullable: true)]
    private ?string $invoicingFramework = null;

    #[ORM\Column(name: 'due_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'payment_terms_note', type: 'string', length: 255, nullable: true)]
    private ?string $paymentTermsNote = null;

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

    #[ORM\Column(name: 'counter_sale_payer', type: 'string', length: 16, nullable: true, enumType: CounterSalePayer::class)]
    private ?CounterSalePayer $counterSalePayer = null;

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

    public function getPayerAttention(): ?string
    {
        return $this->payerAttention;
    }

    public function setPayerAttention(?string $payerAttention): void
    {
        $this->payerAttention = $payerAttention;
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

    public function getCounterSalePayer(): ?CounterSalePayer
    {
        return $this->counterSalePayer;
    }

    public function setCounterSalePayer(?CounterSalePayer $counterSalePayer): void
    {
        $this->counterSalePayer = $counterSalePayer;
    }

    /**
     * @return list<array{rate: string, lineTotal: int, allowance: int, vat: int}>
     */
    public function getTaxBreakdown(): array
    {
        return $this->taxBreakdown;
    }

    /**
     * @param list<array{rate: string, lineTotal: int, allowance: int, vat: int}> $taxBreakdown
     */
    public function setTaxBreakdown(array $taxBreakdown): void
    {
        $this->taxBreakdown = $taxBreakdown;
    }

    public function getTotalNetBeforeAllowance(): int
    {
        return $this->totalNetBeforeAllowance;
    }

    public function setTotalNetBeforeAllowance(int $totalNetBeforeAllowance): void
    {
        $this->totalNetBeforeAllowance = $totalNetBeforeAllowance;
    }

    public function getTotalAllowance(): int
    {
        return $this->totalAllowance;
    }

    public function setTotalAllowance(int $totalAllowance): void
    {
        $this->totalAllowance = $totalAllowance;
    }

    public function getTotalTaxBasis(): int
    {
        return $this->totalTaxBasis;
    }

    public function setTotalTaxBasis(int $totalTaxBasis): void
    {
        $this->totalTaxBasis = $totalTaxBasis;
    }

    public function getTotalTax(): int
    {
        return $this->totalTax;
    }

    public function setTotalTax(int $totalTax): void
    {
        $this->totalTax = $totalTax;
    }

    public function getTotalGross(): int
    {
        return $this->totalGross;
    }

    public function setTotalGross(int $totalGross): void
    {
        $this->totalGross = $totalGross;
    }

    public function getProcessingRule(): ?string
    {
        return $this->processingRule;
    }

    public function setProcessingRule(?string $processingRule): void
    {
        $this->processingRule = $processingRule;
    }

    public function getInvoicingFramework(): ?string
    {
        return $this->invoicingFramework;
    }

    public function setInvoicingFramework(?string $invoicingFramework): void
    {
        $this->invoicingFramework = $invoicingFramework;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function getPaymentTermsNote(): ?string
    {
        return $this->paymentTermsNote;
    }

    public function setPaymentTermsNote(?string $paymentTermsNote): void
    {
        $this->paymentTermsNote = $paymentTermsNote;
    }
}
