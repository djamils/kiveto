<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalClinicMismatchException extends \DomainException
{
    public static function create(string $animalId, string $expectedClinicId, string $actualClinicId): self
    {
        return new self(\sprintf(
            'Animal "%s" belongs to clinic "%s", expected clinic "%s".',
            $animalId,
            $actualClinicId,
            $expectedClinicId
        ));
    }
}
