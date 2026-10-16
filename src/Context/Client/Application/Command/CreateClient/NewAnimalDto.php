<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\CreateClient;

/**
 * An animal the new client comes with, as the Animal context records it
 * (species, sex and reproductive status as its value strings, the birth
 * date as `Y-m-d`).
 */
final readonly class NewAnimalDto
{
    public function __construct(
        public string $name,
        public string $species,
        public string $sex,
        public string $reproductiveStatus,
        public bool $isMixedBreed,
        public ?string $breedName,
        public ?string $birthDate,
        public ?string $microchipNumber,
    ) {
    }
}
