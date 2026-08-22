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
    ) {
    }
}
