<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchInvoices;

final readonly class InvoiceListRow
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public ?string $payerClientId,
        public string $payerName,
        public string $currency,
        public int $totalGrossMinorUnits,
        public int $amountPaidMinorUnits,
        public int $balanceDueMinorUnits,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $issuedAt,
        public ?string $admissionId,
        /** INVOICE or CREDIT_NOTE; a credit note's status is CREDIT_NOTE and its « paid » what it gave back. */
        public string $kind = SearchInvoicesCriteria::KIND_INVOICE,
        public int $creditedMinorUnits = 0,
        public int $refundedMinorUnits = 0,
        /** For a credit note: the invoice it corrects. */
        public ?string $originInvoiceId = null,
        public ?string $originInvoiceNumber = null,
        /** For a credit note: why it was issued. */
        public ?string $reason = null,
        /** For a counter sale: CLIENT, NEW_CLIENT or REFUSED (see CounterSalePayer). */
        public ?string $counterSalePayer = null,
    ) {
    }
}
