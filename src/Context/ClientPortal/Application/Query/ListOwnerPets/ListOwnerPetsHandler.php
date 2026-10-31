<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPets;

use App\Context\ClientPortal\Application\Service\OwnerPets;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListOwnerPetsHandler
{
    public function __construct(private OwnerPets $pets)
    {
    }

    /**
     * @return list<OwnerPet>
     */
    public function __invoke(ListOwnerPets $query): array
    {
        return array_map(OwnerPet::of(...), $this->pets->visible($query->userId, $query->clinicId));
    }
}
