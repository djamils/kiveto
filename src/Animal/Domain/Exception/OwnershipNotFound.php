<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class OwnershipNotFound extends \DomainException
{
    public static function create(string $animalId, string $clientId): self
    {
        return new self(\sprintf('Ownership not found for animal "%s" and client "%s".', $animalId, $clientId));
    }
}
