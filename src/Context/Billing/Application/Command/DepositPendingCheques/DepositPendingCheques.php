<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\DepositPendingCheques;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Remise de chèques »: every cheque still in the drawer goes to the bank.
 */
final readonly class DepositPendingCheques implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $depositedByUserId = null,
    ) {
    }
}
