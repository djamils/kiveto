<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

/**
 * Everything the invoice page and the printable version need, in one read.
 */
final readonly class InvoiceDetails
{
    /**
     * @param list<string>                                                             $payerAddressLines
     * @param list<InvoiceLineView>                                                    $lines
     * @param list<PaymentView>                                                        $payments
     * @param list<array{ratePercent: string, netMinorUnits: int, taxMinorUnits: int}> $taxBreakdown
     * @param list<CreditNoteSummaryView>                                              $creditNotes
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $number,
        public string $status,
        public string $statusLabel,
        public ?string $admissionId,
        public ?string $payerClientId,
        public string $payerName,
        public array $payerAddressLines,
        public array $lines,
        public array $payments,
        public array $taxBreakdown,
        public string $currency,
        public int $totalNetBeforeDiscountMinorUnits,
        public int $discountMinorUnits,
        public ?string $discountKind,
        public ?string $discountValue,
        public ?string $discountReason,
        public int $totalNetMinorUnits,
        public int $totalTaxMinorUnits,
        public int $totalGrossMinorUnits,
        public int $amountPaidMinorUnits,
        public int $balanceDueMinorUnits,
        public ?string $notes,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $cancelledAt,
        public ?string $cancellationReason,
        public int $creditedMinorUnits = 0,
        public int $refundedMinorUnits = 0,
        public int $creditableMinorUnits = 0,
        public array $creditNotes = [],
        /** For a counter sale: CLIENT, NEW_CLIENT or REFUSED (see CounterSalePayer). */
        public ?string $counterSalePayer = null,
    ) {
    }
}
