<?php

declare(strict_types=1);

namespace App\Client\Application\Command\UnarchiveClient;

final readonly class UnarchiveClient
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
