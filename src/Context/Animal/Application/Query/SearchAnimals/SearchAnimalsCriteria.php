<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\SearchAnimals;

final readonly class SearchAnimalsCriteria
{
    public const string SORT_NAME    = 'name';
    public const string SORT_SPECIES = 'species';
    public const string SORT_STATUS  = 'status';
    public const string SORT_CREATED = 'created';

    /**
     * @param list<string>  $speciesList   species to keep, empty means all
     * @param list<string>  $lifeStatuses  life statuses to keep, empty means all
     * @param ?list<string> $restrictToIds animals the caller already narrowed down,
     *                                     null when there is no such restriction
     */
    public function __construct(
        public ?string $searchTerm = null,
        public ?string $status = null,
        public ?string $species = null,
        public ?string $lifeStatus = null,
        public ?string $ownerClientId = null,
        public int $page = 1,
        public int $limit = 20,
        public array $speciesList = [],
        public array $lifeStatuses = [],
        public ?array $restrictToIds = null,
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
        return [self::SORT_NAME, self::SORT_SPECIES, self::SORT_STATUS, self::SORT_CREATED];
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * True when the filters can never match anything.
     */
    public function isImpossible(): bool
    {
        return [] === $this->restrictToIds;
    }
}
