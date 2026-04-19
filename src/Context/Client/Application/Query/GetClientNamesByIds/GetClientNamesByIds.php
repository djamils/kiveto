<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNamesByIds;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClientNamesByIds implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
