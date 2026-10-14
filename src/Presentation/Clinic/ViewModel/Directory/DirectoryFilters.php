<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Directory;

use Symfony\Component\HttpFoundation\Request;

/**
 * The directory's view as carried by the query string — a view is a URL.
 */
final readonly class DirectoryFilters
{
    public const string MODE_CLIENTS = 'clients';
    public const string MODE_ANIMALS = 'animals';

    public const array PAGE_SIZES       = [10, 25, 50, 100];
    public const int DEFAULT_PAGE_SIZE  = 10;
    public const array CLIENT_STATUSES  = ['active', 'archived'];
    public const array DEFAULT_STATUSES = ['active'];
    public const array SPECIES          = ['dog', 'cat', 'nac', 'other'];
    public const string STATE_REMINDER  = 'rappel';
    public const string STATE_UNPAID    = 'impaye';
    public const string STATE_ALERT     = 'vig';
    public const array STATES           = [self::STATE_REMINDER, self::STATE_UNPAID, self::STATE_ALERT];

    /**
     * @param list<string> $statuses
     * @param list<string> $cities
     * @param list<string> $species
     * @param list<string> $states
     */
    public function __construct(
        public string $mode,
        public string $search,
        public array $statuses,
        public array $cities,
        public array $species,
        public array $states,
        public string $direction,
        public int $page,
        public int $pageSize,
    ) {
    }

    /**
     * @param list<string> $knownCities
     */
    public static function fromRequest(Request $request, array $knownCities): self
    {
        $query = $request->query->all();

        // "tab" and "search" are what the previous directory used; old links
        // and the animals redirect still land on the right view.
        $mode = self::scalar($query, 'mode') ?? self::scalar($query, 'tab');
        $mode = self::MODE_ANIMALS === $mode ? self::MODE_ANIMALS : self::MODE_CLIENTS;

        $pageSize = (int) (self::scalar($query, 'per_page') ?? self::DEFAULT_PAGE_SIZE);

        return new self(
            mode: $mode,
            search: trim(self::scalar($query, 'q') ?? self::scalar($query, 'search') ?? ''),
            // Without a status in the URL the directory shows the active clients,
            // as the mock-up does; an explicitly emptied filter shows everyone.
            statuses: \array_key_exists('status', $query)
                ? self::list($query, 'status', self::CLIENT_STATUSES)
                : self::DEFAULT_STATUSES,
            cities: self::list($query, 'city', $knownCities),
            species: self::list($query, 'species', self::SPECIES),
            states: self::list($query, 'etat', self::STATES),
            direction: 'desc' === self::scalar($query, 'dir') ? 'desc' : 'asc',
            page: max(1, (int) (self::scalar($query, 'page') ?? 1)),
            pageSize: \in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : self::DEFAULT_PAGE_SIZE,
        );
    }

    public function isClients(): bool
    {
        return self::MODE_CLIENTS === $this->mode;
    }

    public function has(string $state): bool
    {
        return \in_array($state, $this->states, true);
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function scalar(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<string>            $allowed
     *
     * @return list<string>
     */
    private static function list(array $query, string $key, array $allowed): array
    {
        $raw    = $query[$key] ?? [];
        $values = [];

        foreach (\is_array($raw) ? $raw : [$raw] as $value) {
            if (\is_string($value) && \in_array($value, $allowed, true) && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
