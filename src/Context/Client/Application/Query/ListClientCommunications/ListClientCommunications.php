<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCommunications;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClientCommunications implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
