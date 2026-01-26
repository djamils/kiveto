<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalMustHavePrimaryOwnerException extends \DomainException
{
    public function __construct(string $animalId)
    {
        parent::__construct(\sprintf('Animal "%s" must have exactly one primary owner when active.', $animalId));
    }
}
