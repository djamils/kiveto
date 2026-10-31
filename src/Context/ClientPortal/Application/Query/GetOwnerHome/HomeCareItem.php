<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;

/** A care item of the home, with the pet it is for. */
final readonly class HomeCareItem
{
    public function __construct(
        public string $petId,
        public string $petName,
        public OwnerCareItem $care,
    ) {
    }
}
