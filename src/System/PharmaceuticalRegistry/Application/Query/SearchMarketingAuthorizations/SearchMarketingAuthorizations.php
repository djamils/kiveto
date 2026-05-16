<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchMarketingAuthorizations implements QueryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public string $term,
        public int $limit = 20,
        public array $filters = [],
    ) {
    }
}
