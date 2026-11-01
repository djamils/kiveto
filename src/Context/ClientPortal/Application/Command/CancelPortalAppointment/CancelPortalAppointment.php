<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\CancelPortalAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner cancels one of their appointments. Answers the appointment id.
 */
final readonly class CancelPortalAppointment implements CommandInterface
{
    /**
     * @param ?string $reason unavailable|no_longer_needed|seen_elsewhere|other
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $appointmentId,
        public ?string $reason = null,
    ) {
    }
}
