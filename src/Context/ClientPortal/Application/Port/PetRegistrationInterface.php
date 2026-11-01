<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Registers the owner's new animal at booking time (Animal context, S4g). */
interface PetRegistrationInterface
{
    /**
     * The active animal of that primary owner with that name
     * (case-insensitive) and species, created at or after `since`, or null.
     *
     * @param string $species dog|cat|nac|other
     */
    public function recentPetOf(string $clinicId, string $ownerClientId, string $name, string $species, \DateTimeImmutable $since): ?string;

    /**
     * A new animal, alive, of that client alone, everything else unknown;
     * answers its id.
     *
     * @param string $species dog|cat|nac|other
     */
    public function register(string $clinicId, string $ownerClientId, string $name, string $species): string;
}
