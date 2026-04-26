<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

final readonly class ClientSearchEntryData
{
    public function __construct(
        public string $clientId,
        public string $clinicId,
        public string $firstName,
        public string $lastName,
        public ?string $phone,
        public ?string $email,
        public string $status,
    ) {
    }
}
