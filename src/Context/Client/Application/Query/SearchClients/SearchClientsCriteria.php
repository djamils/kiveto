<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchClients;

final readonly class SearchClientsCriteria
{
    public const string SORT_NAME    = 'name';
    public const string SORT_CITY    = 'city';
    public const string SORT_CREATED = 'created';

    /**
     * @param list<string> $statuses statuses to keep, empty means all
     * @param list<string> $cities   cities to keep, empty means all
     */
    public function __construct(
        public ?string $searchTerm = null,
        public ?string $status = null,
        public int $page = 1,
        public int $limit = 20,
        public array $statuses = [],
        public array $cities = [],
        public string $sort = self::SORT_NAME,
        public string $direction = 'asc',
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100.');
        }

        if (!\in_array($sort, self::sortableColumns(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sort column "%s".', $sort));
        }

        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sort direction "%s".', $direction));
        }
    }

    /**
     * @return list<string>
     */
    public static function sortableColumns(): array
    {
        return [self::SORT_NAME, self::SORT_CITY, self::SORT_CREATED];
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
