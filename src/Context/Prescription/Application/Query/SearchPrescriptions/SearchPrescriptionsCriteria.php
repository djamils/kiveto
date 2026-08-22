<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\SearchPrescriptions;

final readonly class SearchPrescriptionsCriteria
{
    public const string SORT_DATE    = 'date';
    public const string SORT_NUMBER  = 'number';
    public const string SORT_PATIENT = 'patient';
    public const string SORT_STATUS  = 'status';

    /**
     * @param list<string> $statuses
     */
    public function __construct(
        public ?string $term = null,
        public array $statuses = [],
        public ?\DateTimeImmutable $issuedFrom = null,
        public ?\DateTimeImmutable $issuedTo = null,
        public ?bool $controlledOnly = null,
        public string $sortBy = self::SORT_DATE,
        public bool $sortDescending = true,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }

    /**
     * A filter set that can only ever match nothing, so the repository can skip
     * the round trip entirely.
     */
    public function isImpossible(): bool
    {
        return null !== $this->issuedFrom
            && null !== $this->issuedTo
            && $this->issuedFrom > $this->issuedTo;
    }
}
