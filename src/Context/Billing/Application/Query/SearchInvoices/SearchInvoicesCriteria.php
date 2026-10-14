<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchInvoices;

/**
 * @phpstan-type SortField 'date'|'number'|'payer'|'amount'|'status'
 */
final readonly class SearchInvoicesCriteria
{
    public const string SORT_DATE   = 'date';
    public const string SORT_NUMBER = 'number';
    public const string SORT_PAYER  = 'payer';
    public const string SORT_AMOUNT = 'amount';
    public const string SORT_STATUS = 'status';

    public const string KIND_INVOICE     = 'INVOICE';
    public const string KIND_CREDIT_NOTE = 'CREDIT_NOTE';

    /**
     * @param list<string>      $statuses         invoice statuses (credit notes have none)
     * @param list<string>      $kinds            which documents to list: invoices, credit notes or both
     * @param list<string>|null $admissionIds     only the documents of these visits (null: no restriction)
     * @param list<string>      $termAdmissionIds visits the search term also matches (animal, motif…)
     */
    public function __construct(
        public ?string $term = null,
        public array $statuses = [],
        public ?\DateTimeImmutable $issuedFrom = null,
        public ?\DateTimeImmutable $issuedTo = null,
        public ?string $payerClientId = null,
        public string $sortBy = self::SORT_DATE,
        public bool $sortDescending = true,
        public int $page = 1,
        public int $perPage = 25,
        public array $kinds = [self::KIND_INVOICE],
        public ?array $admissionIds = null,
        public array $termAdmissionIds = [],
    ) {
    }

    public function lists(string $kind): bool
    {
        return \in_array($kind, $this->kinds, true);
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
        if ([] === $this->kinds || [] === $this->admissionIds) {
            return true;
        }

        return null !== $this->issuedFrom
            && null !== $this->issuedTo
            && $this->issuedFrom > $this->issuedTo;
    }
}
