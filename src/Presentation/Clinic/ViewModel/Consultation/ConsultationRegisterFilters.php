<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The register's filter bar as the query string carries it
 * (consultations-v-topbar: search, period, status, practitioner, type, page
 * and page size), validated once.
 */
final readonly class ConsultationRegisterFilters
{
    /** Period segments: days of history kept, counted back from today's midnight. */
    public const array PERIODS = ['0' => 0, '7' => 7, '30' => 30, 'tout' => null];

    public const string DEFAULT_PERIOD = '7';

    /** Statuses of the mock-up the clinic records, and the stage each one is. */
    public const array STATUSES = [
        'cours' => ConsultationRegisterCriteria::STAGE_IN_PROGRESS,
        'aclot' => ConsultationRegisterCriteria::STAGE_TO_CLOSE,
        'clot'  => ConsultationRegisterCriteria::STAGE_CLOSED,
    ];

    /** « Type » options the register can answer; `du` is read from the invoices. */
    public const array TYPES = [
        'urg'  => ConsultationRegisterCriteria::KIND_URGENT,
        'chir' => ConsultationRegisterCriteria::KIND_SURGERY,
        'lab'  => ConsultationRegisterCriteria::KIND_LAB,
        'du'   => null,
    ];

    public const array PAGE_SIZES = [25, 50, 100];

    public function __construct(
        public string $q = '',
        public string $per = self::DEFAULT_PERIOD,
        public string $statut = '',
        public string $vet = '',
        public string $type = '',
        public int $page = 1,
        public int $pp = 25,
    ) {
    }

    /**
     * @param ?string $period forces the period (a consultation opened from a link shows « Tout »)
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
            statut: \array_key_exists($query->getString('statut'), self::STATUSES) ? $query->getString('statut') : '',
            vet: Uuid::isValid($vet) ? $vet : '',
            type: \array_key_exists($query->getString('type'), self::TYPES) ? $query->getString('type') : '',
            page: max(1, $query->getInt('page', 1)),
            pp: \in_array($pp, self::PAGE_SIZES, true) ? $pp : 25,
        );
    }

    /**
     * Start of the period, as an instant.
     *
     * @param \DateTimeImmutable $today midnight of today, in the clinic's time zone
     */
    public function startedAfter(\DateTimeImmutable $today): ?\DateTimeImmutable
    {
        $days = self::PERIODS[$this->per] ?? null;

        return null !== $days ? $today->modify(\sprintf('-%d days', $days))->setTimezone(new \DateTimeZone('UTC')) : null;
    }

    public function stage(): ?string
    {
        return self::STATUSES[$this->statut] ?? null;
    }

    public function kind(): ?string
    {
        return self::TYPES[$this->type] ?? null;
    }

    public function isFiltered(): bool
    {
        return '' !== $this->q || '' !== $this->statut || '' !== $this->vet || '' !== $this->type || 'tout' !== $this->per;
    }

    /**
     * @return array{q: string, per: string, statut: string, vet: string, type: string, page: int, pp: int}
     */
    public function toArray(): array
    {
        return [
            'q'      => $this->q,
            'per'    => $this->per,
            'statut' => $this->statut,
            'vet'    => $this->vet,
            'type'   => $this->type,
            'page'   => $this->page,
            'pp'     => $this->pp,
        ];
    }
}
