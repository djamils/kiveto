<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

/**
 * A patient is only ever linked to an animal of its own clinic: an animal id
 * of another clinic would make its owner the payer of this clinic's invoices.
 */
final class AnimalNotInClinicException extends \DomainException
{
    public function __construct(string $animalId, string $clinicId)
    {
        parent::__construct(\sprintf('Animal "%s" is not an animal of clinic "%s".', $animalId, $clinicId));
    }
}
