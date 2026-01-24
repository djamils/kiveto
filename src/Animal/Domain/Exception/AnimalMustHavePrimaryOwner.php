<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalMustHavePrimaryOwner extends \DomainException
{
    public static function create(string $animalId): self
    {
        return new self(\sprintf('Animal "%s" must have exactly one primary owner when active.', $animalId));
    }
}
