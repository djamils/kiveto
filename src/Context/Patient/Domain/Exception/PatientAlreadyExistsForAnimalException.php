<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

final class PatientAlreadyExistsForAnimalException extends \DomainException
{
    public function __construct(string $animalId, string $clinicId)
    {
        parent::__construct(\sprintf(
            'An active patient already exists for animal "%s" in clinic "%s".',
            $animalId,
            $clinicId,
        ));
    }
}
