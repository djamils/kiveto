<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalNotFoundException extends \DomainException
{
    public function __construct(string $animalId)
    {
        parent::__construct(\sprintf('Animal with ID "%s" not found.', $animalId));
    }
}
