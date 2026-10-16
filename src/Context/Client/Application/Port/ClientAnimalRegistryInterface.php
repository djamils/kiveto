<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;

/**
 * Registers, in the Animal context, an animal a client comes with when
 * their record is opened.
 */
interface ClientAnimalRegistryInterface
{
    /**
     * @return string the id of the animal created
     */
    public function register(string $clinicId, string $ownerClientId, NewAnimalDto $animal): string;
}
