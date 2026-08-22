<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

/**
 * What the catalogue page asks for: a page of one family, narrowed by whatever
 * the user picked in the filter bar.
 *
 * `kinds` carries the act category, the article kind or the package pricing
 * mode, depending on the family being listed — one field, because the filter bar
 * has one slot for it.
 */
final readonly class CatalogListCriteria
{
    public const string SORT_NAME     = 'name';
    public const string SORT_CODE     = 'code';
    public const string SORT_PRICE    = 'price';
    public const string SORT_CATEGORY = 'category';
    public const string SORT_UPDATED  = 'updated';

    public const string STATUS_ACTIVE   = 'ACTIVE';
    public const string STATUS_ARCHIVED = 'ARCHIVED';

    /**
     * @param list<string> $kinds
     * @param list<string> $taxCategories
     */
    public function __construct(
        public ?string $term = null,
        public array $kinds = [],
        public array $taxCategories = [],
        public ?string $status = self::STATUS_ACTIVE,
        public ?bool $requiresPrescription = null,
        public ?bool $controlledOnly = null,
        public string $sortBy = self::SORT_NAME,
        public bool $sortDescending = false,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }
}
