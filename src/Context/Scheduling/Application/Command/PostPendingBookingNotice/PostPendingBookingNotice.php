<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PostPendingBookingNotice;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Tells the appointment's practitioner, in the bell, that an online booking
 * waits for their confirmation. Queued on `async` by the booking: the
 * booking transaction never reads another bounded context for it.
 *
 * `replace` when the request changed practitioner: the entry of the former
 * one is withdrawn first (still unread), in the same job so that the order
 * holds.
 */
final readonly class PostPendingBookingNotice implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public bool $replace = false,
    ) {
    }
}
