<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Domain\Exception\PetNotEligibleException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;

/**
 * The pets an owner may ask a change for (S6b): visible to the account
 * (active, primary owner one of its confirmed-linked clients, not
 * transferred) and alive. A deceased or missing pet, or an archived pet of
 * one of its clients, is not eligible; any other pet is not found.
 */
final readonly class PortalRequestPets
{
    public function __construct(
        private OwnerPets $pets,
        private PetDirectoryInterface $directory,
    ) {
    }

    /**
     * @throws PetNotFoundException    when not one of the account's pets
     * @throws PetNotEligibleException when deceased, missing or archived
     */
    public function eligible(string $userId, string $clinicId, string $petId): PortalPet
    {
        $pet = $this->pets->find($userId, $clinicId, $petId);

        if (null !== $pet) {
            return $pet->isAlive() ? $pet : throw new PetNotEligibleException($petId, $pet->lifeStatus);
        }

        $state = $this->directory->statesOf($clinicId, [$petId])[$petId] ?? null;

        if (null !== $state && $state->archived && \in_array($state->primaryOwnerClientId, $this->pets->clientIds($userId, $clinicId), true)) {
            throw new PetNotEligibleException($petId, 'archived');
        }

        throw new PetNotFoundException($petId);
    }
}
