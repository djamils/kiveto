<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListAnimalSummariesPerClientIds implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
        public int $limit = 5,
    ) {
    }
}
