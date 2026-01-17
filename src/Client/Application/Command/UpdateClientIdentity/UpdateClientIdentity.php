<?php

declare(strict_types=1);

namespace App\Client\Application\Command\UpdateClientIdentity;

final readonly class UpdateClientIdentity
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $firstName,
        public string $lastName,
    ) {
    }
}
