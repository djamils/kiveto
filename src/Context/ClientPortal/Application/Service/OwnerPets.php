<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;

/**
 * The pets an owner account sees in a clinic: those of the client files it
 * reaches through a confirmed link there, read from the links themselves,
 * never from client ids handed over by the caller.
 */
final readonly class OwnerPets
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private PetDirectoryInterface $directory,
    ) {
    }

    /**
     * Alive pets first, then by name.
     *
     * @return list<PortalPet>
     */
    public function visible(string $userId, string $clinicId): array
    {
        $clientIds = [];

        foreach ($this->links->findConfirmedByUser($userId) as $link) {
            if ($link->clinicId() === $clinicId) {
                $clientIds[] = $link->clientId();
            }
        }

        return $this->directory->visiblePets($clinicId, array_values(array_unique($clientIds)));
    }

    /**
     * @return list<PortalPet>
     */
    public function alive(string $userId, string $clinicId): array
    {
        return array_values(array_filter($this->visible($userId, $clinicId), static fn (PortalPet $pet): bool => $pet->isAlive()));
    }

    public function find(string $userId, string $clinicId, string $petId): ?PortalPet
    {
        foreach ($this->visible($userId, $clinicId) as $pet) {
            if ($pet->id === $petId) {
                return $pet;
            }
        }

        return null;
    }

    /** @throws PetNotFoundException when the pet is not visible to the account */
    public function get(string $userId, string $clinicId, string $petId): PortalPet
    {
        return $this->find($userId, $clinicId, $petId) ?? throw new PetNotFoundException($petId);
    }
}
