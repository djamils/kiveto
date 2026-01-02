<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\SearchTranslations;

final readonly class TranslationSearchResult
{
    /**
     * @param list<TranslationSearchItem> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
