<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

final readonly class ClientSearchRow
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $searchPhone,
        public ?string $primaryEmail,
        public string $status,
    ) {
    }
}
