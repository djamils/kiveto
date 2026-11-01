<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawPendingBookingNotice;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Removes the « RDV en ligne à confirmer » bell entry of a request that was
 * confirmed, declined or withdrawn.
 */
final readonly class WithdrawPendingBookingNotice implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
