<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestListingBooking;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A first appointment asked through the clinic directory (S7d) by an owner
 * the clinic does not know yet, for a pet it does not know either. Answers
 * the booking request id.
 */
final readonly class RequestListingBooking implements CommandInterface
{
    /**
     * @param string             $petSpecies dog|cat|nac|other
     * @param \DateTimeImmutable $startsAt   a start offered by the listing's availability
     */
    public function __construct(
        public string $userId,
        public string $listingId,
        public string $petName,
        public string $petSpecies,
        public string $motifId,
        public string $practitionerId,
        public \DateTimeImmutable $startsAt,
    ) {
    }
}
