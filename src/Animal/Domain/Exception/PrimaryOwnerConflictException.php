<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class PrimaryOwnerConflictException extends \DomainException
{
    public function __construct(string $animalId)
    {
        parent::__construct(\sprintf('Animal "%s" cannot have multiple primary owners.', $animalId));
    }
}
