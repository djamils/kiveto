<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListPortalPets;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPortalPetsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<PortalPetView> alive pets first, then by name
     */
    public function __invoke(ListPortalPets $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->readRepository->listPortalPets(
            ClinicId::fromString($query->clinicId),
            $query->clientIds,
        );
    }
}
