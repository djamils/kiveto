<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerCareItems;

use App\Context\ClientPortal\Application\Query\GetOwnerHome\HomeCareItem;
use App\Context\ClientPortal\Application\Service\CareItems;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListOwnerCareItemsHandler
{
    public function __construct(
        private OwnerPets $pets,
        private CareItems $careItems,
    ) {
    }

    /**
     * @return list<OwnerCareItem>
     */
    public function __invoke(ListOwnerCareItems $query): array
    {
        $pet = $this->pets->get($query->userId, $query->clinicId, $query->petId);

        return array_map(static fn (HomeCareItem $item): OwnerCareItem => $item->care, $this->careItems->of($query->clinicId, [$pet]));
    }
}
