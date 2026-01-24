<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalAlreadyArchived extends \DomainException
{
    public static function create(string $animalId): self
    {
        return new self(\sprintf('Animal "%s" is already archived.', $animalId));
    }
}
