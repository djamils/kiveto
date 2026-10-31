<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class InvoicePublication
{
    public function __construct(
        public string $invoiceId,
        public string $payerClientId,
        public string $number,
        public \DateTimeImmutable $issuedAt,
        public ?string $admissionId,
    ) {
    }
}
