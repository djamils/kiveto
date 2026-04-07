<?php

declare(strict_types=1);

namespace App\Animal\Domain\Exception;

final class MicrochipAlreadyUsedException extends \DomainException
{
    public function __construct(string $microchipNumber, string $clinicId)
    {
        parent::__construct(\sprintf('Microchip number "%s" is already used in clinic "%s".', $microchipNumber, $clinicId));
    }
}
