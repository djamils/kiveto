<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchCompanyRegistry;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Raison sociale, SIREN ou SIRET »: the companies of the national registry
 * it may be. A read-only lookup answering a user, outside any transaction.
 */
final readonly class SearchCompanyRegistry implements QueryInterface
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LIMIT  = 10;

    public function __construct(
        public string $query,
        public int $limit = 8,
    ) {
    }
}
