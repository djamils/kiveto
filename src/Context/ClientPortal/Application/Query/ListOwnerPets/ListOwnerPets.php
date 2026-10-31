<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPets;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The pets an owner account sees in a clinic, deceased and missing ones
 * included. Answers list<OwnerPet>, alive pets first, then by name.
 */
final readonly class ListOwnerPets implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
