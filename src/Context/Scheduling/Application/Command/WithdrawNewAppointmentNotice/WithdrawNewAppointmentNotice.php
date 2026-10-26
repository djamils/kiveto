<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Removes the « Nouveau RDV » entry of a cancelled appointment from the bell
 * while it is still unread (queued on `async` by the cancellation).
 */
final readonly class WithdrawNewAppointmentNotice implements CommandInterface
{
    public function __construct(public string $appointmentId)
    {
    }
}
