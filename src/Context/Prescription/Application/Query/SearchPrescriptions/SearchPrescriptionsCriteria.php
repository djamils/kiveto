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
     * A signed ordonnance that can still be presented.
     */
    public const string VALIDITY_CURRENT = 'current';

    /**
     * A signed ordonnance whose validity has run out.
     */
    public const string VALIDITY_EXPIRED = 'expired';

    /**
     * @param list<string> $statuses
     * @param ?string      $prescriberId user id of the practitioner who signed, null for anyone
     * @param ?string      $validity     one of the VALIDITY_* constants, null to ignore validity
     */
    public function __construct(
        public ?string $term = null,
        public array $statuses = [],
        public ?\DateTimeImmutable $issuedFrom = null,
        public ?\DateTimeImmutable $issuedTo = null,
        public ?bool $controlledOnly = null,
        public ?string $prescriberId = null,
        public ?string $validity = null,
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
