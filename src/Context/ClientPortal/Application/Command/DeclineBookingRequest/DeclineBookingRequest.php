<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeclineBookingRequest;

use App\Shared\Application\Bus\CommandInterface;

/** Staff decline a « Nouveau client » request from « Demandes Portal ». */
final readonly class DeclineBookingRequest implements CommandInterface
{
    /**
     * @param string $actorName the staff member's name, kept on the request
     */
    public function __construct(
        public string $clinicId,
        public string $requestId,
        public string $actorUserId,
        public string $actorName,
    ) {
    }
}
