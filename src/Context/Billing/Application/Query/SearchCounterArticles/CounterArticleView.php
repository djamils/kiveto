<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchCounterArticles;

/**
 * An article the desk may sell, with its price all taxes included.
 */
final readonly class CounterArticleView
{
    public function __construct(
        public string $articleId,
        public string $label,
        public ?string $code,
        public int $unitNetMinorUnits,
        public int $unitGrossMinorUnits,
        public string $taxRatePercent,
        public string $currency,
    ) {
    }
}
