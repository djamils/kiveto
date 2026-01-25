<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class MicrochipAlreadyUsedException extends \DomainException
{
    public static function create(string $microchipNumber, string $clinicId): self
    {
        return new self(\sprintf('Microchip number "%s" is already used in clinic "%s".', $microchipNumber, $clinicId));
    }
}
