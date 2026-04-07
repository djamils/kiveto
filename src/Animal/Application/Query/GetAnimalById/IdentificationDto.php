<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class IdentificationDto
{
    public function __construct(
        public ?string $microchipNumber,
        public ?string $tattooNumber,
        public ?string $passportNumber,
        public string $registryType,
        public ?string $registryNumber,
        public ?string $sireNumber,
    ) {
    }
}
