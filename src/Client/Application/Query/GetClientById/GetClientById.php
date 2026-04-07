<?php

declare(strict_types=1);

namespace App\Client\Application\Query\GetClientById;

final readonly class GetClientById
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
