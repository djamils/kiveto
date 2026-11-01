<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawPendingOnlineAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A pending online request withdrawn for its author whatever the
 * cancellation window (the owner account is deleted, slice 7): the slot is
 * freed. Anything no longer pending, or unknown, is left as it is.
 */
final readonly class WithdrawPendingOnlineAppointment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
    ) {
    }
}
