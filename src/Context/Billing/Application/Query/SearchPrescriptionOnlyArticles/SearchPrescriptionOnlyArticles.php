<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchPrescriptionOnlyArticles;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The prescription-only medicines a search at the counter also finds, so the
 * desk is told where they are dispensed from.
 */
final readonly class SearchPrescriptionOnlyArticles implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $term,
        public int $limit = 5,
    ) {
    }
}
