<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalClinicMismatchException extends \DomainException
{
    public function __construct(string $animalId, string $expectedClinicId, string $actualClinicId)
    {
        parent::__construct(\sprintf(
            'Animal "%s" belongs to clinic "%s", expected clinic "%s".',
            $animalId,
            $actualClinicId,
            $expectedClinicId
        ));
    }
}
