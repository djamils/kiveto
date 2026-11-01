<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * A pet of the account that is deceased, missing or archived: no change
 * request on it. The API answers 422 `pet_not_eligible`.
 */
final class PetNotEligibleException extends \DomainException
{
    public function __construct(public readonly string $petId, public readonly string $state)
    {
        parent::__construct('Cet animal ne peut pas faire l\'objet d\'une demande.');
    }
}
