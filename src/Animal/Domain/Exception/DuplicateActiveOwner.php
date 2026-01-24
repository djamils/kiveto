<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class DuplicateActiveOwner extends \DomainException
{
    public static function create(string $animalId, string $clientId): self
    {
        return new self(\sprintf('Client "%s" is already an active owner of animal "%s".', $clientId, $animalId));
    }
}
