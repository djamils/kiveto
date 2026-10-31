<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The animals an owner account may see (Animal context). */
interface PetDirectoryInterface
{
    /**
     * Active animals whose active primary owner is one of the clients, never
     * sold or given; alive ones first, then by name.
     *
     * @param list<string> $clientIds
     *
     * @return list<PortalPet>
     */
    public function visiblePets(string $clinicId, array $clientIds): array;

    /** The identity of an animal of the clinic, whatever its owner, or null when unknown. */
    public function identityOf(string $clinicId, string $animalId): ?PetIdentity;
}
