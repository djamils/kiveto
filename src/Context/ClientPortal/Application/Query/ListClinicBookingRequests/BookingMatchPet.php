<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

/** An animal staff may confirm the request for. */
final readonly class BookingMatchPet
{
    /**
     * @param string $species dog|cat|nac|other
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $species,
    ) {
    }
}
