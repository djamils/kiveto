<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Prescription;

use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The register's filter bar as the query string carries it
 * (ordonnances-v-topbar_3: search, period, status, prescriber, medicines,
 * page and page size), validated once and turned into search criteria.
 */
final readonly class PrescriptionRegisterFilters
{
    /** Period segments: days of history kept, counted back from today's midnight. */
    public const array PERIODS = ['0' => 0, '7' => 7, '30' => 30, 'tout' => null];

    public const string DEFAULT_PERIOD = '30';

    /** Statuses of the mock-up: the document's life cycle, expiry included. */
    public const array STATUSES = ['brou', 'emise', 'exp', 'ann'];

    /** « Médicaments » options the register can actually answer. */
    public const array CLASSES = ['ctrl'];

    public const array PAGE_SIZES = [25, 50, 100];

    public function __construct(
        public string $q = '',
        public string $per = self::DEFAULT_PERIOD,
        public string $statut = '',
        public string $vet = '',
        public string $classe = '',
        public int $page = 1,
        public int $pp = 25,
    ) {
    }

    /**
     * @param ?string $period forces the period (the details page opens on « Tout »)
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
            vet: Uuid::isValid($vet) ? $vet : '',
            classe: \in_array($query->getString('classe'), self::CLASSES, true) ? $query->getString('classe') : '',
            page: max(1, $query->getInt('page', 1)),
            pp: \in_array($pp, self::PAGE_SIZES, true) ? $pp : 25,
        );
    }

    /**
     * @param \DateTimeImmutable $today midnight of today, in the clinic's time zone
     */
    public function criteria(\DateTimeImmutable $today, ?int $page = null): SearchPrescriptionsCriteria
    {
        $days = self::PERIODS[$this->per] ?? null;

        return new SearchPrescriptionsCriteria(
            term: '' !== $this->q ? $this->q : null,
            statuses: match ($this->statut) {
                'brou' => ['DRAFT'],
                'emise', 'exp' => ['ISSUED'],
                'ann'   => ['CANCELLED'],
                default => [],
            },
            issuedFrom: null !== $days
                ? $today->modify(\sprintf('-%d days', $days))->setTimezone(new \DateTimeZone('UTC'))
                : null,
            controlledOnly: 'ctrl' === $this->classe ? true : null,
            prescriberId: '' !== $this->vet ? $this->vet : null,
            validity: match ($this->statut) {
                'emise' => SearchPrescriptionsCriteria::VALIDITY_CURRENT,
                'exp'   => SearchPrescriptionsCriteria::VALIDITY_EXPIRED,
                default => null,
            },
            page: $page ?? $this->page,
            perPage: $this->pp,
        );
    }

    /**
     * @return array{q: string, per: string, statut: string, vet: string, classe: string, page: int, pp: int}
     */
    public function toArray(): array
    {
        return [
            'q'      => $this->q,
            'per'    => $this->per,
            'statut' => $this->statut,
            'vet'    => $this->vet,
            'classe' => $this->classe,
            'page'   => $this->page,
            'pp'     => $this->pp,
        ];
    }
}
