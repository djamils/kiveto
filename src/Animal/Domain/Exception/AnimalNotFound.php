<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalNotFound extends \DomainException
{
    public static function withId(string $animalId): self
    {
        return new self(\sprintf('Animal with ID "%s" not found.', $animalId));
    }
}
