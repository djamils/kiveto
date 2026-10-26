<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * A clinic as its appointment messages print it.
 */
final readonly class ClinicCard
{
    /**
     * @param string|null $email   the letterhead e-mail (organizer of the calendar invitation)
     * @param string|null $address « 18 rue Lepic, 75018 Paris »
     */
    public function __construct(
        public string $name,
        public \DateTimeZone $timeZone,
        public ?string $email,
        public ?string $address,
    ) {
    }
}
