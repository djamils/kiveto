<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class DuplicateActiveOwnerException extends \DomainException
{
    public function __construct(string $animalId, string $clientId)
    {
        parent::__construct(\sprintf('Client "%s" is already an active owner of animal "%s".', $clientId, $animalId));
    }
}
