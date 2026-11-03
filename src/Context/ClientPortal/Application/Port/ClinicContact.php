<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** How the clinic's clients reach it, as its letterhead prints it. */
final readonly class ClinicContact
{
    public function __construct(
        /** The phone as the clinic recorded it; null when it has none. */
        public ?string $phone,
        /** Null when the letterhead has no street line or no city. */
        public ?ClinicAddress $address,
    ) {
    }
}
