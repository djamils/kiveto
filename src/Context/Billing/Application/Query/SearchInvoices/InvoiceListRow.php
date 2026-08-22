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
    ) {
    }
}
