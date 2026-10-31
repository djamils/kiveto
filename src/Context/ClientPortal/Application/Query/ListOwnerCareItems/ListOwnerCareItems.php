<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerCareItems;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The care items of one pet of the owner account, nearest due date first;
 * empty for a deceased or missing pet. Answers list<OwnerCareItem>; throws
 * PetNotFoundException when the pet is not visible to the account.
 */
final readonly class ListOwnerCareItems implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petId,
    ) {
    }
}
