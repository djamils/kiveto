<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\SearchConsultations;

/**
 * Validated shape the read repository works from.
 *
 * The animal identifiers of the query have already been turned into patient
 * identifiers by the handler, since the consultation row carries a patient.
 */
final readonly class SearchConsultationsCriteria
{
    public const string SORT_DATETIME = 'datetime';
    public const string SORT_STATUS   = 'status';
    public const string SORT_AMOUNT   = 'amount';
    public const string SORT_VET      = 'vet';

    /**
     * @param list<string>  $textMatchPatientIds
     * @param ?list<string> $restrictToPatientIds
     * @param list<string>  $statuses
     * @param list<string>  $practitionerUserIds
     * @param list<string>  $practitionerOrder
     */
    public function __construct(
        public ?string $searchTerm = null,
        public array $textMatchPatientIds = [],
        public ?array $restrictToPatientIds = null,
        public ?\DateTimeImmutable $startedAfterUtc = null,
        public array $statuses = [],
        public array $practitionerUserIds = [],
        public array $practitionerOrder = [],
        public string $sort = self::SORT_DATETIME,
        public string $direction = 'desc',
        public int $page = 1,
        public int $limit = 25,
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
        return [self::SORT_DATETIME, self::SORT_STATUS, self::SORT_AMOUNT, self::SORT_VET];
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * True when the filters can never match anything, so the repository can
     * answer without touching the database.
     */
    public function isImpossible(): bool
    {
        return [] === $this->restrictToPatientIds;
    }
}
