<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueCreditNote;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Établir un avoir » on an issued invoice.
 *
 * @param list<string> $lineIds      the invoice lines to credit
 * @param ?string      $refundMethod how what the client overpaid is given back
 *                                   (a PaymentMethod), null to keep it as a credit
 */
final readonly class IssueCreditNote implements CommandInterface
{
    /**
     * @param list<string> $lineIds
     */
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public array $lineIds,
        public string $reason,
        public ?string $refundMethod = null,
        public ?string $issuedByUserId = null,
    ) {
    }
}
