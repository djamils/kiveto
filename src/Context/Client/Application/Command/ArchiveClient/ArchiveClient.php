<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\ArchiveClient;

final readonly class ArchiveClient
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
