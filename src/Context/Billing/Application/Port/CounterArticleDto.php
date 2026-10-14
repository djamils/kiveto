<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

final readonly class CounterArticleDto
{
    public function __construct(
        public string $articleId,
        public string $label,
        public ?string $code,
        public int $unitNetMinorUnits,
        public string $currency,
        public ?string $taxCategoryCode,
    ) {
    }
}
