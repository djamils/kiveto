<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

/**
 * A credit note as its invoice lists it.
 */
final readonly class CreditNoteSummaryView
{
    /**
     * @param list<string> $creditedLineIds the invoice lines it cancels out
     */
    public function __construct(
        public string $id,
        public string $number,
        public int $totalGrossMinorUnits,
        public string $reason,
        public ?string $refundMethod,
        public ?string $refundMethodLabel,
        public int $refundedMinorUnits,
        public \DateTimeImmutable $issuedAt,
        public array $creditedLineIds,
    ) {
    }
}
