<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A credit note as stored: frozen at issue, its lines kept as a JSON snapshot
 * and its totals stored so the register can list it beside the invoices.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_credit_note_clinic_issued', columns: ['clinic_id', 'issued_at'])]
#[ORM\Index(name: 'idx_credit_note_invoice', columns: ['invoice_id'])]
#[ORM\UniqueConstraint(name: 'uniq_credit_note_number', columns: ['clinic_id', 'number'])]
class CreditNoteEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $number;

    #[ORM\Column(name: 'invoice_id', type: UuidType::NAME)]
    private Uuid $invoiceId;

    #[ORM\Column(name: 'invoice_number', type: 'string', length: 20)]
    private string $invoiceNumber;

    #[ORM\Column(name: 'payer_client_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $payerClientId = null;

    #[ORM\Column(name: 'payer_name', type: 'string', length: 255)]
    private string $payerName;

    #[ORM\Column(name: 'payer_attention', type: 'string', length: 255, nullable: true)]
    private ?string $payerAttention = null;

    /** @var list<string> */
    #[ORM\Column(name: 'payer_address_lines', type: 'json')]
    private array $payerAddressLines = [];

    /** @var list<array<string, int|string|null>> */
    #[ORM\Column(name: 'line_items', type: 'json')]
    private array $lines = [];

    #[ORM\Column(type: 'string', length: 255)]
    private string $reason;

    #[ORM\Column(name: 'refund_method', type: 'string', length: 20, nullable: true, enumType: PaymentMethod::class)]
    private ?PaymentMethod $refundMethod = null;

    #[ORM\Column(name: 'refunded_minor_units', type: 'integer')]
    private int $refundedMinorUnits = 0;

    #[ORM\Column(name: 'total_net_minor_units', type: 'integer')]
    private int $totalNetMinorUnits = 0;

    #[ORM\Column(name: 'total_tax_minor_units', type: 'integer')]
    private int $totalTaxMinorUnits = 0;

    #[ORM\Column(name: 'total_gross_minor_units', type: 'integer')]
    private int $totalGrossMinorUnits = 0;

    /** @var list<array{rate: string, lineTotal: int, allowance: int, vat: int}> */
    #[ORM\Column(name: 'tax_breakdown', type: 'json')]
    private array $taxBreakdown = [];

    #[ORM\Column(name: 'total_net_before_allowance', type: 'integer', options: ['default' => 0])]
    private int $totalNetBeforeAllowance = 0;

    #[ORM\Column(name: 'total_allowance', type: 'integer', options: ['default' => 0])]
    private int $totalAllowance = 0;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(name: 'issued_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'issued_by_user_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $issuedByUserId = null;

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

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getInvoiceId(): Uuid
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(Uuid $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
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

    /**
     * @return list<array<string, int|string|null>>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @param list<array<string, int|string|null>> $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getRefundMethod(): ?PaymentMethod
    {
        return $this->refundMethod;
    }

    public function setRefundMethod(?PaymentMethod $refundMethod): void
    {
        $this->refundMethod = $refundMethod;
    }

    public function getRefundedMinorUnits(): int
    {
        return $this->refundedMinorUnits;
    }

    public function setRefundedMinorUnits(int $refundedMinorUnits): void
    {
        $this->refundedMinorUnits = $refundedMinorUnits;
    }

    public function getTotalNetMinorUnits(): int
    {
        return $this->totalNetMinorUnits;
    }

    public function setTotalNetMinorUnits(int $totalNetMinorUnits): void
    {
        $this->totalNetMinorUnits = $totalNetMinorUnits;
    }

    public function getTotalTaxMinorUnits(): int
    {
        return $this->totalTaxMinorUnits;
    }

    public function setTotalTaxMinorUnits(int $totalTaxMinorUnits): void
    {
        $this->totalTaxMinorUnits = $totalTaxMinorUnits;
    }

    public function getTotalGrossMinorUnits(): int
    {
        return $this->totalGrossMinorUnits;
    }

    public function setTotalGrossMinorUnits(int $totalGrossMinorUnits): void
    {
        $this->totalGrossMinorUnits = $totalGrossMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): void
    {
        $this->issuedAt = $issuedAt;
    }

    public function getIssuedByUserId(): ?Uuid
    {
        return $this->issuedByUserId;
    }

    public function setIssuedByUserId(?Uuid $issuedByUserId): void
    {
        $this->issuedByUserId = $issuedByUserId;
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
}
