<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * What Scheduling says became of a pending portal booking (D19): when it is
 * a new client's request, the request follows.
 */
final readonly class SyncBookingRequestFromAppointment implements CommandInterface
{
    public const string CONFIRMED = 'confirmed';
    public const string DECLINED  = 'declined';
    public const string EXPIRED   = 'expired';
    public const string CANCELLED = 'cancelled';

    /**
     * @param string  $outcome confirmed|declined|expired|cancelled
     * @param ?string $ownerId the client file of a confirmed appointment
     */
    public function __construct(
        public string $appointmentId,
        public string $outcome,
        public ?string $ownerId = null,
    ) {
    }
}
