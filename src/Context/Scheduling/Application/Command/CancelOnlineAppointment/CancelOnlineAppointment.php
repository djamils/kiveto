<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CancelOnlineAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A client cancels their appointment on the Portal. Answers the
 * appointment id.
 */
final readonly class CancelOnlineAppointment implements CommandInterface
{
    /**
     * @param ?string $cancellationReason unavailable|no_longer_needed|seen_elsewhere|other
     */
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public ?string $cancellationReason = null,
    ) {
    }
}
