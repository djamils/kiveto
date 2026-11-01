<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

/** A client file of the clinic whose mobile is the requester's phone, as staff check it. */
final readonly class BookingClientMatch
{
    /**
     * @param list<BookingMatchPet> $pets                   its active animals (primary owner), alive first, then by name
     * @param bool                  $nameMismatch           its last name differs from the Portal profile's (case and accents ignored)
     * @param bool                  $linkedToAnotherAccount another Portal account has a confirmed link to it
     */
    public function __construct(
        public string $clientId,
        public string $firstName,
        public string $lastName,
        public array $pets,
        public bool $nameMismatch,
        public bool $linkedToAnotherAccount,
    ) {
    }

    public function name(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
