<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\CountClients;

use App\Shared\Application\Bus\QueryInterface;

final readonly class CountClients implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public ?string $status = null,
    ) {
    }
}
