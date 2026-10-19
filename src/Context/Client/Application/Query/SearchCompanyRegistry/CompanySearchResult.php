<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchCompanyRegistry;

use App\Context\Client\Application\Port\CompanySummary;

/**
 * The registry's hits, or why it could not answer: `throttled` (try again in
 * a moment, keep what is shown) or `down` (fall back to manual entry).
 */
final readonly class CompanySearchResult
{
    public const string THROTTLED = 'throttled';
    public const string DOWN      = 'down';

    /**
     * @param list<CompanySummary> $hits
     */
    public function __construct(
        public bool $available,
        public ?string $reason,
        public array $hits,
    ) {
    }
}
