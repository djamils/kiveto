<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The owner sees the pet, but it is deceased or missing: it cannot be
 * booked. The API answers 422 `pet_not_bookable`.
 */
final class PetNotBookableException extends \DomainException
{
    public function __construct(public readonly string $petId, public readonly string $lifeStatus)
    {
        parent::__construct('Cet animal ne peut pas recevoir de rendez-vous en ligne.');
    }
}
