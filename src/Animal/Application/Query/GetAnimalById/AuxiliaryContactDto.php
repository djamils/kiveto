<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class AuxiliaryContactDto
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phoneNumber,
    ) {
    }
}
