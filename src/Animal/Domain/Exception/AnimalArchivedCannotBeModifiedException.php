<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class AnimalArchivedCannotBeModifiedException extends \DomainException
{
    public function __construct(string $animalId)
    {
        parent::__construct(\sprintf('Animal "%s" is archived and cannot be modified.', $animalId));
    }
}
