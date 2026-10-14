<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The register's filter bar as the query string carries it
 * (facturation-v-topbar_3: search, period, status, settlement, practitioner,
 * page and page size), validated once and turned into search criteria.
 */
final readonly class BillingRegisterFilters
{
    /** Period segments: days of history kept, counted back from today's midnight. */
    public const array PERIODS = ['0' => 0, '7' => 7, '30' => 30, 'tout' => null];

    public const string DEFAULT_PERIOD = '30';

    /** « Statut » of the mock-up: the document's life, credit notes apart. */
    public const array STATUSES = ['brou', 'emise', 'annav', 'av'];

    /** « Règlement »: still owed, overdue, settled. */
    public const array SETTLEMENTS = ['du', 'retard', 'solde'];

    public const array PAGE_SIZES = [25, 50, 100];

    /** A private client's invoice is overdue once it has waited this long. */
    public const int OVERDUE_AFTER_DAYS = 30;

    private const array STATUS_SETS = [
        'brou'  => [InvoiceStatus::DRAFT],
        'emise' => [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::PAID],
        'annav' => [InvoiceStatus::CREDITED, InvoiceStatus::CANCELLED],
    ];

    private const array SETTLEMENT_SETS = [
        'du'     => [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID],
        'retard' => [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID],
        'solde'  => [InvoiceStatus::PAID, InvoiceStatus::CREDITED],
    ];

    public function __construct(
        public string $q = '',
        public string $per = self::DEFAULT_PERIOD,
        public string $statut = '',
        public string $pay = '',
        public string $vet = '',
        public int $page = 1,
        public int $pp = 25,
    ) {
    }

    /**
     * @param ?string $period forces the period (a document's page opens on « Tout »)
     */
    public static function fromRequest(Request $request, ?string $period = null): self
    {
        $query = $request->query;
        $per   = $period ?? $query->getString('per', self::DEFAULT_PERIOD);
        $vet   = $query->getString('vet');
        $pp    = $query->getInt('pp', 25);

        return new self(
            q: mb_substr(mb_trim($query->getString('q')), 0, 100),
            per: \array_key_exists($per, self::PERIODS) ? $per : self::DEFAULT_PERIOD,
            statut: \in_array($query->getString('statut'), self::STATUSES, true) ? $query->getString('statut') : '',
            pay: \in_array($query->getString('pay'), self::SETTLEMENTS, true) ? $query->getString('pay') : '',
            vet: Uuid::isValid($vet) ? $vet : '',
            page: max(1, $query->getInt('page', 1)),
            pp: \in_array($pp, self::PAGE_SIZES, true) ? $pp : 25,
        );
    }

    /**
     * @param \DateTimeImmutable $today          midnight of today, in the clinic's time zone
     * @param list<string>|null  $vetAdmissions  the visits of the practitioner filtered on
     * @param list<string>       $termAdmissions the visits the search term matches (animal, motif)
     */
    public function criteria(
        \DateTimeImmutable $today,
        ?array $vetAdmissions = null,
        array $termAdmissions = [],
        ?int $page = null,
    ): SearchInvoicesCriteria {
        $days     = self::PERIODS[$this->per] ?? null;
        $utc      = new \DateTimeZone('UTC');
        $statuses = $this->statuses();
        $kinds    = $this->kinds();

        // Statut and Règlement that cannot both hold (« Brouillon » and « Soldée »).
        if (null !== $statuses && [] === $statuses) {
            $kinds = [];
        }

        return new SearchInvoicesCriteria(
            term: '' !== $this->q ? $this->q : null,
            statuses: array_map(static fn (InvoiceStatus $status): string => $status->value, $statuses ?? []),
            issuedFrom: null !== $days ? $today->modify(\sprintf('-%d days', $days))->setTimezone($utc) : null,
            issuedTo: 'retard' === $this->pay
                ? $today->modify(\sprintf('-%d days', self::OVERDUE_AFTER_DAYS))->setTimezone($utc)
                : null,
            page: $page ?? $this->page,
            perPage: $this->pp,
            kinds: $kinds,
            admissionIds: $vetAdmissions,
            termAdmissionIds: $termAdmissions,
        );
    }

    /**
     * @return array{q: string, per: string, statut: string, pay: string, vet: string, page: int, pp: int}
     */
    public function toArray(): array
    {
        return [
            'q'      => $this->q,
            'per'    => $this->per,
            'statut' => $this->statut,
            'pay'    => $this->pay,
            'vet'    => $this->vet,
            'page'   => $this->page,
            'pp'     => $this->pp,
        ];
    }

    /**
     * Invoice statuses both filters allow: null when neither restricts them.
     *
     * @return list<InvoiceStatus>|null
     */
    private function statuses(): ?array
    {
        $sets = array_values(array_filter([
            self::STATUS_SETS[$this->statut] ?? null,
            self::SETTLEMENT_SETS[$this->pay] ?? null,
        ]));

        if ([] === $sets) {
            return null;
        }

        $statuses = $sets[0];

        foreach (\array_slice($sets, 1) as $set) {
            $statuses = array_values(array_filter($statuses, static fn (InvoiceStatus $status): bool => \in_array($status, $set, true)));
        }

        return $statuses;
    }

    /**
     * @return list<string>
     */
    private function kinds(): array
    {
        if ('av' === $this->statut) {
            return '' === $this->pay ? [SearchInvoicesCriteria::KIND_CREDIT_NOTE] : [];
        }

        if ('' !== $this->statut || '' !== $this->pay) {
            return [SearchInvoicesCriteria::KIND_INVOICE];
        }

        return [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE];
    }
}
