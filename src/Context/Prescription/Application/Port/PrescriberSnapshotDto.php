<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

final readonly class PrescriberSnapshotDto
{
    public function __construct(
        public string $userId,
        public string $displayName,
        public ?string $registrationNumber = null,
        public ?string $professionalTitle = null,
    ) {
    }
}
