<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerPet;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One pet of the owner account, with its latest weight, masked chip and
 * referent vet. Answers OwnerPetDetails, or null when the pet is not
 * visible to the account (unknown, archived, secondary owner, sold, given).
 */
final readonly class GetOwnerPet implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petId,
    ) {
    }
}
