<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * An issued invoice still expecting money, as the unpaid reminders select it.
 */
final readonly class OutstandingInvoiceRow
{
    public function __construct(
        public string $id,
        public string $number,
        public ?string $payerClientId,
        public \DateTimeImmutable $issuedAt,
        public int $balanceDueMinorUnits,
    ) {
    }
}
