<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

final readonly class PaymentView
{
    public function __construct(
        public string $id,
        public string $method,
        public string $methodLabel,
        public int $amountMinorUnits,
        public \DateTimeImmutable $receivedAt,
        public ?string $reference,
        public ?string $recordedByUserId = null,
        /** When the cheque went to the bank; null for any other payment, or a cheque still in the drawer. */
        public ?\DateTimeImmutable $depositedAt = null,
    ) {
    }
}
