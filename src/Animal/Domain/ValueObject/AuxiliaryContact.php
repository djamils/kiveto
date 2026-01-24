<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

final readonly class AuxiliaryContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phoneNumber,
    ) {
    }
}
