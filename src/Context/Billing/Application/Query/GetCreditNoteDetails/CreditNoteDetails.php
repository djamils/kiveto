<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCreditNoteDetails;

use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceLineView;
use App\Context\Billing\Application\Query\GetInvoiceDetails\TransmissionView;

/**
 * Everything the sheet and the printable credit note need.
 */
final readonly class CreditNoteDetails
{
    /**
     * @param list<string>                                                                                                             $payerAddressLines
     * @param list<InvoiceLineView>                                                                                                    $lines
     * @param list<array{ratePercent: string, netMinorUnits: int, taxMinorUnits: int, grossMinorUnits: int, allowanceMinorUnits: int}> $taxBreakdown
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $number,
        public string $invoiceId,
        public string $invoiceNumber,
        public ?\DateTimeImmutable $invoiceIssuedAt,
        public ?string $admissionId,
        public ?string $payerClientId,
        public string $payerName,
        public array $payerAddressLines,
        public array $lines,
        public array $taxBreakdown,
        public string $currency,
        public int $totalNetMinorUnits,
        public int $totalTaxMinorUnits,
        public int $totalGrossMinorUnits,
        public string $reason,
        public ?string $refundMethod,
        public ?string $refundMethodLabel,
        public int $refundedMinorUnits,
        public \DateTimeImmutable $issuedAt,
        public ?string $issuedByUserId,
        /** « À l'attention de » line under a structure's name. */
        public ?string $payerAttention = null,
        public string $processingRule = 'B2C',
        public ?string $payerSiren = null,
        public ?string $payerVatNumber = null,
        public int $discountMinorUnits = 0,
        public ?TransmissionView $transmission = null,
    ) {
    }
}
