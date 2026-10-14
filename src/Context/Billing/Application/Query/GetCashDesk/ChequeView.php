<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

final readonly class ChequeView
{
    public function __construct(
        public string $paymentId,
        public string $invoiceId,
        public ?string $invoiceNumber,
        public string $payerName,
        public ?string $reference,
        public int $amountMinorUnits,
        public string $currency,
        public \DateTimeImmutable $receivedAt,
    ) {
    }
}
