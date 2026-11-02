<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use Symfony\Component\HttpFoundation\Request;

/**
 * The « Demandes » filter bar as the query string carries it
 * (demandes-v-topbar: search, status segment, type menu, page and page
 * size), validated once.
 */
final readonly class PortalRequestRegisterFilters
{
    /** Status segments: « À traiter », « Traitées », « Refusées », « Toutes ». */
    public const array STATUSES = ['submitted', 'done', 'rejected', self::ALL];

    public const string ALL = 'tout';

    public const string DEFAULT_STATUS = 'submitted';

    public const array PAGE_SIZES = [25, 50, 100];

    public function __construct(
        public string $statut = self::DEFAULT_STATUS,
        public string $type = '',
        public string $q = '',
        public int $page = 1,
        public int $pp = 25,
    ) {
    }

    /**
     * @param string $ownStatus the segment to show when the address names none (a request opened from the bell shows the list of its own state)
     */
    public static function fromRequest(Request $request, string $ownStatus = self::DEFAULT_STATUS): self
    {
        $query  = $request->query;
        $statut = $query->has('statut') ? $query->getString('statut') : $ownStatus;
        // The former « Toutes » link carried an empty status.
        $statut = '' === $statut ? self::ALL : $statut;
        $type   = $query->getString('type');
        $pp     = $query->getInt('pp', 25);

        return new self(
            statut: \in_array($statut, self::STATUSES, true) ? $statut : self::DEFAULT_STATUS,
            type: \in_array($type, PortalRequestPageBuilder::KINDS, true) ? $type : '',
            q: mb_substr(mb_trim($query->getString('q')), 0, 100),
            page: max(1, $query->getInt('page', 1)),
            pp: \in_array($pp, self::PAGE_SIZES, true) ? $pp : 25,
        );
    }

    /** The status a request must have to be listed, null for « Toutes ». */
    public function status(): ?string
    {
        return self::ALL === $this->statut ? null : $this->statut;
    }

    /**
     * @return array{statut: string, type: string, q: string, page: int, pp: int}
     */
    public function toArray(): array
    {
        return [
            'statut' => $this->statut,
            'type'   => $this->type,
            'q'      => $this->q,
            'page'   => $this->page,
            'pp'     => $this->pp,
        ];
    }
}
