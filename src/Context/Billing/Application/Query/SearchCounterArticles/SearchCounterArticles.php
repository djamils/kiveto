<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchCounterArticles;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchCounterArticles implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $term = '',
        public int $limit = 20,
    ) {
    }
}
