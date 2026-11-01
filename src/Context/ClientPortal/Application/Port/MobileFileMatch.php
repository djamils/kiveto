<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * A client file, in any active clinic, whose `mobile` phone is the owner
 * account's (Client context). Only the clinic is ever shown to the owner;
 * the names and animals are for the clinic's staff.
 */
final readonly class MobileFileMatch
{
    /**
     * @param list<string> $animalNames
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $firstName,
        public string $lastName,
        public array $animalNames,
    ) {
    }
}
