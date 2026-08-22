<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CheckoutAdmission;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Takes the money at the desk: issues the invoice if it is still a draft,
 * records what was collected, and ends the visit once nothing is left due.
 */
final readonly class CheckoutAdmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $method,
        public int $amountMinorUnits,
        public ?string $reference = null,
        public ?string $recordedByUserId = null,
    ) {
    }
}
