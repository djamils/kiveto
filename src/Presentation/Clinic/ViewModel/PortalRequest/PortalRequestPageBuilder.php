<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\ClientPortal\Application\Query\GetBookingRequest\GetBookingRequest;
use App\Context\ClientPortal\Application\Query\GetClinicLinkDecision\GetClinicLinkDecision;
use App\Context\ClientPortal\Application\Query\GetClinicLinkRequest\GetClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\GetPortalRequest;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\PortalRequestDetails;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingClientMatch;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingMatchPet;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\LinkRequestFile;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ClinicPortalRequest;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionLineView;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\ListPrescriptionPortalSummaries;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\PrescriptionPortalSummary;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListItemView;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptions;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Presentation\Clinic\ViewModel\Dashboard\PatientFactsReader;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The « Demandes » page (docs/frontend-theme/kiveto-theme/responsive/
 * demandes-v-topbar_1.html): the register of what owners send from the
 * Portal — its pages, counters and filters — and the side sheet of one
 * request, from the ClientPortal queries (plus the pet, the client file and
 * the prescription the request is about), as the page module's JSON. Owner
 * free text (notes, proposed values, Portal profile names) is handed as
 * plain strings: the page module writes it as text, never as HTML.
 * « Rattachement » requests (pending links, then the clinic's recorded
 * answer) and « Nouveau client » requests (first appointments through the
 * directory) are listed while they wait and once answered, with who
 * answered and when.
 *
 * @phpstan-type RequestRow array{
 *     id: string, kind: string, type: string, short: string, subject: string, note: ?string,
 *     petName: string, petEmoji: string, clientName: string, isNew: bool,
 *     status: string, statusLabel: string, statusClass: string, day: string, age: ?string, late: bool,
 *     resolvedBy: ?string, resolvedDay: ?string, urgent: bool, condition: ?array{cls: string, label: string},
 *     flags: list<string>, unreachable: bool, url: string, sheet: string, at: int
 * }
 * @phpstan-type Entry array{id: string, kind: string, status: string, at: int, urgent: bool, item: ClinicPortalRequest|ClinicLinkRequest|ClinicBookingRequest}
 * @phpstan-type Ref array{id: string, url: string, sheet: string}
 * @phpstan-type Register array{
 *     rows: list<RequestRow>, total: int, page: int, pp: int,
 *     counts: array<string, ?int>, kinds: array<string, int>,
 *     head: array{pending: int, urgent: int, late: int, blank: bool, first: array{submitted: ?Ref, urgent: ?Ref, late: ?Ref}, next: list<Ref>}
 * }
 */
final readonly class PortalRequestPageBuilder
{
    public const array STATUSES = ['submitted', 'done', 'rejected'];

    public const array KINDS = [self::CHANGE, self::RENEWAL, self::LINK, self::BOOKING];

    public const string CHANGE = 'pet_change';

    public const string RENEWAL = 'prescription_renewal';

    public const string LINK = 'client_link';

    public const string BOOKING = 'booking_request';

    /** The mock-up's names of the four kinds: type key, long and short label. */
    public const array TYPES = [
        self::CHANGE  => ['modif', 'Modification de fiche', 'Modification'],
        self::RENEWAL => ['renouv', 'Renouvellement', 'Renouvellement'],
        self::LINK    => ['ratt', 'Rattachement de compte', 'Rattachement'],
        self::BOOKING => ['nouveau', 'Nouveau client', 'Nouveau client'],
    ];

    public const array SPECIES_EMOJI = ['dog' => '🐕', 'cat' => '🐈', 'nac' => '🐇'];

    private const array STATUS_CLASS = ['submitted' => 's-att', 'done' => 's-clot', 'rejected' => 's-ann', 'closed' => 's-clos'];

    /** The « Traitée par » of a request no staff member answered from « Demandes ». */
    private const array SYSTEM = [
        'expired'         => 'Système · délai expiré',
        'cancelled'       => 'Agenda · créneau annulé',
        'account_deleted' => 'Système · compte supprimé',
    ];

    private const array CONDITION_CLASS = ['improved' => 'et-ok', 'no_improvement' => 'et-am', 'adverse_effects' => 'et-crit'];

    /** The pet's issued prescriptions read to find the one written for a renewal. */
    private const int CANDIDATES = 25;

    /** A request waiting longer than this is « en retard ». */
    private const int LATE_MINUTES = 1440;

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private PortalRequestQueue $queue,
        private ClockInterface $clock,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    /**
     * Everything the « Demandes » page module starts from: the register's
     * first page and counters, the filters, the answer tokens and — when the
     * address names one — the sheet of the request to open straight away.
     *
     * @param ?array<string, mixed> $sheet requestSheet(), linkSheet() or bookingSheet()
     *
     * @return array<string, mixed>
     */
    public function page(string $clinicId, PortalRequestRegisterFilters $filters, ?array $sheet = null): array
    {
        return [
            'filters'  => $filters->toArray(),
            'register' => $this->register($clinicId, $filters),
            'sheet'    => $sheet,
            'tokens'   => [
                'request' => $this->csrf->getToken('portal_request')->getValue(),
                'link'    => $this->csrf->getToken('portal_link_request')->getValue(),
                'booking' => $this->csrf->getToken('portal_booking_request')->getValue(),
            ],
            'urls' => [
                'list'      => $this->urls->generate('clinic_portal_requests'),
                'search'    => $this->urls->generate('clinic_portal_requests_search'),
                'directory' => $this->urls->generate('clinic_clients_list'),
            ],
        ];
    }

    /**
     * One page of the register: the four kinds merged (changes and renewals
     * of any state, « Rattachement » and « Nouveau client » requests while
     * they wait), filtered, searched (client, animal, subject, note), sorted
     * — the waiting ones first, urgent then the oldest, the answered ones
     * newest first — and sliced; the counters of the segments, of the type
     * menu and of the page head, over the whole queue.
     *
     * @return Register
     */
    public function register(string $clinicId, PortalRequestRegisterFilters $filters): array
    {
        $now    = $this->clock->now();
        $status = $filters->status();
        $open   = $this->entries($this->queue->pending($clinicId), $this->queue->links($clinicId), $this->queue->bookings($clinicId));
        // « À traiter » (the search runs on every keystroke) never reads the
        // answered requests, unless nothing waits: « blank » needs to know
        // whether anything was ever received.
        $history = 'submitted' !== $status || [] === $open
            ? $this->entries($this->queue->history($clinicId), $this->queue->links($clinicId, true), $this->queue->bookings($clinicId, true))
            : null;
        $entries    = [...$open, ...$history ?? []];
        $medication = $this->medicationReader($clinicId, $entries);

        usort($entries, static fn (array $a, array $b): int => self::order($a) <=> self::order($b) ?: ('submitted' === $a['status'] ? strcmp($a['id'], $b['id']) : strcmp($b['id'], $a['id'])));

        $counts = null === $history
            ? ['submitted' => 0, 'done' => null, 'rejected' => null, PortalRequestRegisterFilters::ALL => null]
            : ['submitted' => 0, 'done' => 0, 'rejected' => 0, PortalRequestRegisterFilters::ALL => \count($entries)];
        $kinds   = array_fill_keys(self::KINDS, 0);
        $pending = [];

        foreach ($entries as $entry) {
            if (\array_key_exists($entry['status'], $counts)) {
                $counts[$entry['status']] = ($counts[$entry['status']] ?? 0) + 1;
            }

            if ('submitted' === $entry['status']) {
                ++$kinds[$entry['kind']];
                $pending[] = $entry;
            }
        }

        $urgent = array_values(array_filter($pending, static fn (array $e): bool => $e['urgent']));
        $late   = array_values(array_filter($pending, fn (array $e): bool => $this->minutesSince($e['at'], $now) >= self::LATE_MINUTES));
        $needle = self::folded($filters->q);
        $listed = array_values(array_filter($entries, fn (array $e): bool => (null === $status || $e['status'] === $status)
            && ('' === $filters->type || $e['kind'] === $filters->type)
            && ('' === $needle || str_contains(self::folded($this->haystack($e['item'], $medication)), $needle))));

        $total = \count($listed);
        $page  = min($filters->page, max(1, (int) ceil($total / $filters->pp)));
        $slice = \array_slice($listed, ($page - 1) * $filters->pp, $filters->pp);
        $rows  = [];
        $items = [];

        foreach ($slice as $entry) {
            $rows[] = $this->entryRow($entry['item'], $medication);

            if ($entry['item'] instanceof ClinicPortalRequest) {
                $items[] = $entry['item'];
            }
        }

        $this->speciesOf($clinicId, $items, $rows);

        return [
            'rows'   => $rows,
            'total'  => $total,
            'page'   => $page,
            'pp'     => $filters->pp,
            'counts' => $counts,
            'kinds'  => $kinds,
            'head'   => [
                'pending' => \count($pending),
                'urgent'  => \count($urgent),
                'late'    => \count($late),
                'blank'   => [] === $entries,
                'first'   => [
                    'submitted' => null === ($pending[0] ?? null) ? null : $this->ref($pending[0]),
                    'urgent'    => null === ($urgent[0] ?? null) ? null : $this->ref($urgent[0]),
                    'late'      => null === ($late[0] ?? null) ? null : $this->ref($late[0]),
                ],
                // « Demande suivante »: the next ones in the answering order.
                'next' => array_map($this->ref(...), \array_slice($pending, 0, 4)),
            ],
        ];
    }

    /**
     * The sheet of a change or a renewal; null when it is not this clinic's.
     *
     * @param bool $canPrescribe the member may answer a renewal (`create_prescription`)
     *
     * @return ?array<string, mixed>
     */
    public function requestSheet(string $clinicId, string $id, bool $canPrescribe): ?array
    {
        $request = $this->queryBus->ask(new GetPortalRequest($clinicId, $id));

        if (!$request instanceof PortalRequestDetails) {
            return null;
        }

        return [
            ...$this->detail($request, $clinicId),
            'canResolve' => self::RENEWAL !== $request->kind || $canPrescribe,
        ];
    }

    /**
     * The sheet of a « Rattachement »; null when nothing waits (answered, another clinic's).
     *
     * @return ?array<string, mixed>
     */
    public function linkSheet(string $clinicId, string $userId): ?array
    {
        $request = $this->queryBus->ask(new GetClinicLinkRequest($clinicId, $userId));

        return $request instanceof ClinicLinkRequest ? $this->linkDetail($request, $clinicId) : null;
    }

    /**
     * The sheet of an answered « Rattachement », by its decision; null when it is not this clinic's.
     *
     * @return ?array<string, mixed>
     */
    public function linkDecisionSheet(string $clinicId, string $decisionId): ?array
    {
        $request = $this->queryBus->ask(new GetClinicLinkDecision($clinicId, $decisionId));

        return $request instanceof ClinicLinkRequest ? $this->linkDetail($request, $clinicId) : null;
    }

    /**
     * The sheet of a « Nouveau client » request; null when it is not this clinic's.
     *
     * @return ?array<string, mixed>
     */
    public function bookingSheet(string $clinicId, string $id): ?array
    {
        $request = $this->queryBus->ask(new GetBookingRequest($clinicId, $id));

        return $request instanceof ClinicBookingRequest ? $this->bookingDetail($request) : null;
    }

    /**
     * The list segment of a sheet's own state, for a request opened without filters (the bell, the dashboard).
     *
     * @param array<string, mixed> $sheet
     */
    public static function ownStatus(array $sheet): string
    {
        $status = $sheet['status'] ?? null;

        return \is_string($status) && \in_array($status, self::STATUSES, true) ? $status : PortalRequestRegisterFilters::ALL;
    }

    /**
     * A change or a renewal: what the owner asked, the pet and the client,
     * the values side by side or the prescription to renew, the answer.
     *
     * @return array<string, mixed>
     */
    public function detail(PortalRequestDetails $request, string $clinicId): array
    {
        $isRenewal = self::RENEWAL === $request->kind;
        $weight    = 'weight' === $request->field ? $request->normalisedValue['weightGrams'] ?? null : null;
        $measured  = 'weight' === $request->field ? $request->normalisedValue['measuredOn'] ?? null : null;
        $animal    = $this->animal($clinicId, $request->petId);
        $client    = $this->client($clinicId, $request->clientId);
        $subject   = $isRenewal
            ? $this->medication($clinicId, $request->prescriptionId) ?? PortalRequestText::label(PortalRequestText::CONDITION, $request->petCondition)
            : $this->field($request->field);

        return [
            ...$this->head($request->kind, $subject, $request->submittedAt, $request->status, PortalRequestText::label(PortalRequestText::STATUS, $request->status)),
            'type'              => 'change',
            'id'                => $request->id,
            'url'               => $this->urls->generate('clinic_portal_requests_show', ['id' => $request->id]),
            'sheet'             => $this->urls->generate('clinic_portal_request_sheet', ['id' => $request->id]),
            'actions'           => ['resolve' => $this->urls->generate('clinic_portal_requests_resolve', ['id' => $request->id])],
            'kind'              => $request->kind,
            'isRenewal'         => $isRenewal,
            'field'             => $request->field,
            'fieldLabel'        => $this->field($request->field),
            'condition'         => $request->petCondition,
            'conditionLabel'    => PortalRequestText::label(PortalRequestText::CONDITION, $request->petCondition),
            'conditionClass'    => self::CONDITION_CLASS[$request->petCondition ?? ''] ?? '',
            'urgent'            => 'adverse_effects' === $request->petCondition && 'submitted' === $request->status,
            'petName'           => $request->petName ?? 'Animal inconnu',
            'petEmoji'          => self::SPECIES_EMOJI[null === $animal ? '' : $animal->species] ?? '🐾',
            'petFacts'          => null === $animal ? null : $this->facts($animal),
            'clientName'        => $request->clientName ?? 'Client inconnu',
            'clientPhone'       => null === $client ? null : PatientFactsReader::phone($client),
            'petUrl'            => $this->urls->generate('clinic_animals_view', ['id' => $request->petId]),
            'clientUrl'         => $this->urls->generate('clinic_clients_view', ['id' => $request->clientId]),
            'note'              => $request->note,
            'closedReason'      => null === $request->closedReason ? null : PortalRequestText::label(PortalRequestText::CLOSED_REASON, $request->closedReason),
            'submittedAt'       => $this->when($request->submittedAt),
            'resolvedAt'        => null === $request->resolvedAt ? null : SheetText::date($request->resolvedAt, 'd MMM') . ' à ' . $request->resolvedAt->format('H:i'),
            'resolvedBy'        => $request->resolvedByName,
            'resolutionMessage' => $request->resolutionMessage,
            'unreachable'       => $request->clientUnreachable,
            'values'            => $isRenewal ? null : [
                'atSubmit' => PortalRequestText::value($request->field, $request->currentValueAtSubmit),
                'current'  => PortalRequestText::value($request->field, $request->currentValue),
                'proposed' => \is_int($weight) ? PortalRequestText::kilograms($weight) : PortalRequestText::value($request->field, $request->proposedValue),
                'gap'      => \is_int($weight) ? $this->gap($request->currentValue, $weight) : null,
            ],
            'measuredOn' => \is_string($measured) ? SheetText::date(new \DateTimeImmutable($measured), 'd MMMM y') : null,
            // One click to the animal sheet, its weight form filled in: staff still record it.
            'weighUrl' => \is_int($weight) && 'submitted' === $request->status ? $this->urls->generate('clinic_animals_view', array_filter([
                'id'       => $request->petId,
                'pesee'    => str_replace('.', ',', (string) ($weight / 1000)),
                'pesee_le' => \is_string($measured) ? $measured : null,
            ])) : null,
            'prescription'             => $isRenewal ? $this->prescription($clinicId, $request->prescriptionId) : null,
            'resultingPrescriptionUrl' => null === $request->resultingPrescriptionId ? null : $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $request->resultingPrescriptionId]),
            'candidates'               => $isRenewal && 'submitted' === $request->status ? $this->candidates($clinicId, $request) : [],
        ];
    }

    /**
     * A « Rattachement » request: the Portal profile next to each file
     * (name, mobile, animals) with the « Nom différent » and « Déjà relié à
     * un autre compte Portal » badges.
     *
     * @return array<string, mixed>
     */
    public function linkDetail(ClinicLinkRequest $request, string $clinicId): array
    {
        $files = array_map(function (LinkRequestFile $file) use ($clinicId): array {
            $client = $this->client($clinicId, $file->clientId);

            return [
                'clientId'        => $file->clientId,
                'name'            => $file->name() ?? 'Client inconnu',
                'url'             => $this->urls->generate('clinic_clients_view', ['id' => $file->clientId]),
                'petNames'        => $file->petNames,
                'phone'           => null === $client ? null : PatientFactsReader::phone($client),
                'since'           => null === $client ? null : substr($client->createdAt, 0, 4),
                'city'            => $client?->postalAddress?->city,
                'mismatch'        => $file->nameMismatch,
                'linkedElsewhere' => $file->linkedToAnotherAccount,
            ];
        }, $request->files);

        $status = self::linkStatus($request);

        return [
            ...$this->head(self::LINK, $request->hasNameMismatch() ? 'Nom différent' : 'Fiche correspondante', $request->requestedAt, $status, PortalRequestText::label(PortalRequestText::STATUS, $status)),
            ...$this->linkRef($request),
            'type'    => 'link',
            'actions' => $request->isDecided() ? null : [
                'confirm' => $this->urls->generate('clinic_portal_link_requests_confirm', ['userId' => $request->userId]),
                'reject'  => $this->urls->generate('clinic_portal_link_requests_reject', ['userId' => $request->userId]),
            ],
            'decided'         => $request->isDecided(),
            'resolvedBy'      => $request->decidedByName,
            'resolvedAt'      => null === $request->decidedAt ? null : $this->when($request->decidedAt),
            'userId'          => $request->userId,
            'ownerName'       => $request->ownerName() ?? 'Profil Portal sans nom',
            'requestedOn'     => SheetText::date($request->requestedAt, 'd MMM'),
            'mismatch'        => $request->hasNameMismatch(),
            'linkedElsewhere' => $request->hasFileLinkedElsewhere(),
            'petNames'        => array_merge(...array_map(static fn (LinkRequestFile $file): array => $file->petNames, $request->files)),
            'files'           => $files,
        ];
    }

    /**
     * A « Nouveau client » request: who asks, for which new pet and slot,
     * the clinic's files with that mobile (their animals, badges), what
     * staff may confirm it with.
     *
     * @return array<string, mixed>
     */
    public function bookingDetail(ClinicBookingRequest $request): array
    {
        $petName = $request->petName ?? 'Animal non renseigné';
        $label   = match ($request->status) {
            'pending'   => 'À traiter',
            'confirmed' => 'Confirmée',
            default     => PortalRequestText::label(PortalRequestText::DECLINE_REASON, $request->declineReason),
        };

        return [
            ...$this->head(self::BOOKING, [] === $request->matches ? 'RDV ' . $this->long($request->startAt) : 'Fiche existante possible', $request->createdAt, self::bookingStatus($request), $label),
            'type'    => 'booking',
            'id'      => $request->id,
            'url'     => $this->urls->generate('clinic_portal_booking_requests_show', ['id' => $request->id]),
            'sheet'   => $this->urls->generate('clinic_portal_booking_request_sheet', ['id' => $request->id]),
            'actions' => [
                'confirm' => $this->urls->generate('clinic_portal_booking_requests_confirm', ['id' => $request->id]),
                'decline' => $this->urls->generate('clinic_portal_booking_requests_decline', ['id' => $request->id]),
            ],
            'ownerName'   => $request->requesterName() ?? 'Profil Portal sans nom',
            'phone'       => null === $request->requesterPhone ? null : PatientFactsReader::formatPhone($request->requesterPhone),
            'petName'     => $petName,
            'petEmoji'    => self::SPECIES_EMOJI[$request->petSpecies ?? ''] ?? '🐾',
            'petSpecies'  => PortalRequestText::label(PortalRequestText::SPECIES, $request->petSpecies),
            'motif'       => $request->motif,
            'slot'        => $this->when($request->startAt),
            'slotLong'    => SheetText::date($request->startAt, 'EEE d MMM') . ' · ' . $request->startAt->format('H:i'),
            'submittedAt' => $this->when($request->createdAt),
            'pending'     => 'pending' === $request->status,
            'state'       => $request->status,
            // The sheet names a staff member only: the expiry, the agenda, the account deletion are its status label.
            'resolvedBy'       => $request->resolvedByName,
            'resolvedAt'       => null === $request->resolvedAt ? null : $this->when($request->resolvedAt),
            'agendaUrl'        => $this->urls->generate('clinic_scheduling_agenda', ['view' => 'day', 'date' => $request->startAt->format('Y-m-d')]),
            'attachedClientId' => $request->attachedClientId,
            'matches'          => array_map(fn (BookingClientMatch $match): array => [
                'clientId'        => $match->clientId,
                'name'            => $match->name(),
                'url'             => $this->urls->generate('clinic_clients_view', ['id' => $match->clientId]),
                'mismatch'        => $match->nameMismatch,
                'linkedElsewhere' => $match->linkedToAnotherAccount,
                'attached'        => $match->clientId === $request->attachedClientId,
                'pets'            => array_map(static fn (BookingMatchPet $pet): array => [
                    'id'    => $pet->id,
                    'label' => $pet->name . ' (' . PortalRequestText::label(PortalRequestText::SPECIES, $pet->species) . ')',
                    'emoji' => self::SPECIES_EMOJI[$pet->species] ?? '🐾',
                    // Suggested when it looks like the new pet (same name and species)
                    'same' => null !== $request->petName && $pet->species === $request->petSpecies && mb_strtolower($pet->name) === mb_strtolower($request->petName),
                ], $match->pets),
            ], $request->matches),
        ];
    }

    /**
     * The sheet head: type icon and title, when it came, its state.
     *
     * @return array{typeKey: string, title: string, received: string, status: string, statusLabel: string, statusClass: string}
     */
    private function head(string $kind, string $subject, \DateTimeImmutable $at, string $status, string $statusLabel): array
    {
        return [
            'typeKey'     => self::TYPES[$kind][0],
            'title'       => self::TYPES[$kind][1] . ' · ' . $subject,
            'received'    => $this->day($at, true),
            'status'      => $status,
            'statusLabel' => $statusLabel,
            'statusClass' => self::STATUS_CLASS[$status] ?? 's-clos',
        ];
    }

    /**
     * The pet's issued prescriptions issued after the request, newest first:
     * « Traiter » may link the one written for it.
     *
     * @return list<array{id: string, label: string}>
     */
    private function candidates(string $clinicId, PortalRequestDetails $request): array
    {
        $submitted = $request->submittedAt->setTimezone(new \DateTimeZone('UTC'));
        $page      = $this->queryBus->ask(new SearchPrescriptions($clinicId, new SearchPrescriptionsCriteria(
            statuses: ['ISSUED'],
            issuedFrom: $submitted,
            perPage: self::CANDIDATES,
            animalId: $request->petId,
        )));
        \assert($page instanceof PrescriptionListItemView);

        $candidates = [];

        foreach ($page->items as $row) {
            if (null === $row->issuedAt || $row->issuedAt <= $submitted) {
                continue;
            }

            $candidates[] = [
                'id'    => $row->id,
                'label' => \sprintf('%s · %s · %s', $row->number ?? 'Ordonnance', $this->when($row->issuedAt->setTimezone($request->submittedAt->getTimezone())), $row->prescriberName),
            ];
        }

        return $candidates;
    }

    /**
     * The prescription a renewal is about: its first medicine, posology,
     * who wrote it and when.
     *
     * @return ?array{number: string, url: string, medication: string, posology: ?string, origin: string, validUntil: ?string}
     */
    private function prescription(string $clinicId, ?string $prescriptionId): ?array
    {
        $details = null === $prescriptionId ? null : $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $prescriptionId));

        if (!$details instanceof PrescriptionDetails) {
            return null;
        }

        $line = $details->lines[0] ?? null;
        $line = $line instanceof PrescriptionLineView ? $line : null;

        return [
            'number'     => $details->number ?? 'Ordonnance',
            'url'        => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $details->id]),
            'medication' => null === $line ? 'Ordonnance sans médicament' : $line->label . ('' === $line->quantity ? '' : ' · ' . $line->quantity),
            'posology'   => $line?->posology,
            'origin'     => trim((null === $details->issuedAt ? 'rédigée' : 'prescrite le ' . SheetText::date($details->issuedAt, 'd MMM y')) . ' par ' . $details->prescriberName),
            'validUntil' => null === $details->validUntil ? null : SheetText::date($details->validUntil, 'd MMM y'),
        ];
    }

    /** The first medicine of a prescription, the renewal's subject. */
    private function medication(string $clinicId, ?string $prescriptionId): ?string
    {
        $details = null === $prescriptionId ? null : $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $prescriptionId));
        $line    = $details instanceof PrescriptionDetails ? $details->lines[0] ?? null : null;

        return $line instanceof PrescriptionLineView ? $line->label : null;
    }

    private function animal(string $clinicId, string $animalId): ?AnimalView
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\Exception) {
            return null;
        }

        return $animal instanceof AnimalView ? $animal : null;
    }

    private function client(string $clinicId, string $clientId): ?ClientView
    {
        $client = $this->queryBus->ask(new GetClientById($clinicId, $clientId));

        return $client instanceof ClientView ? $client : null;
    }

    /** « Golden retriever · 8 ans ». */
    private function facts(AnimalView $animal): ?string
    {
        $facts = array_filter([
            $animal->breedName ?? PortalRequestText::SPECIES[$animal->species] ?? null,
            PatientFactsReader::age($animal, $this->clock->now()),
        ]);

        return [] === $facts ? null : implode(' · ', $facts);
    }

    /**
     * « +3,3 % » from the weight on file (grams) to the proposed one.
     *
     * @return ?array{label: string, strong: bool}
     */
    private function gap(?string $current, int $proposed): ?array
    {
        if (null === $current || !is_numeric($current) || 0 === (int) $current) {
            return null;
        }

        $percent = ($proposed - (int) $current) / (int) $current * 100;

        return [
            'label'  => ($percent > 0 ? '+' : '') . number_format($percent, 1, ',', ' ') . ' %',
            'strong' => abs($percent) >= 10,
        ];
    }

    private function field(?string $field): string
    {
        return 'other' === $field ? 'Autre demande' : PortalRequestText::label(PortalRequestText::FIELD, $field);
    }

    /**
     * The register's order: the waiting ones first, urgent then the one
     * waiting the longest; the answered ones newest first (the caller
     * breaks ties on the id, time-ordered).
     *
     * @param Entry $entry
     *
     * @return array{int, int, int}
     */
    private static function order(array $entry): array
    {
        return 'submitted' === $entry['status']
            ? [0, $entry['urgent'] ? 0 : 1, $entry['at']]
            : [1, 0, -$entry['at']];
    }

    /** Lower case, accents aside: « Médor » is found with « medor ». */
    private static function folded(string $text): string
    {
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

        return mb_strtolower((string) preg_replace('/\p{Mn}/u', '', \is_string($decomposed) ? $decomposed : $text));
    }

    /**
     * What the search reads of a request: client, animal, kind, subject, note.
     *
     * @param \Closure(?string): ?string $medication
     */
    private function haystack(ClinicPortalRequest|ClinicLinkRequest|ClinicBookingRequest $item, \Closure $medication): string
    {
        if ($item instanceof ClinicLinkRequest) {
            return implode(' ', [
                self::TYPES[self::LINK][1],
                $item->ownerName() ?? '',
                $item->hasNameMismatch() ? 'Nom différent' : 'Fiche correspondante',
                ...array_map(static fn (LinkRequestFile $file): string => ($file->name() ?? '') . ' ' . implode(' ', $file->petNames), $item->files),
            ]);
        }

        if ($item instanceof ClinicBookingRequest) {
            return implode(' ', [self::TYPES[self::BOOKING][1], $item->requesterName() ?? '', $item->petName ?? '', $item->motif]);
        }

        return implode(' ', [
            self::TYPES[$item->kind][1] ?? '',
            $item->petName ?? '',
            $item->clientName ?? '',
            $item->note ?? '',
            self::RENEWAL === $item->kind
                ? ($medication($item->prescriptionId) ?? '') . ' ' . PortalRequestText::label(PortalRequestText::CONDITION, $item->petCondition)
                : $this->field($item->field) . ' ' . ($item->proposedValue ?? ''),
        ]);
    }

    /**
     * The first medicine of the renewals' prescriptions, read in one go the
     * first time one is needed (a search, a renewal on the page).
     *
     * @param list<Entry> $entries
     *
     * @return \Closure(?string): ?string
     */
    private function medicationReader(string $clinicId, array $entries): \Closure
    {
        $known = null;

        return function (?string $prescriptionId) use ($clinicId, $entries, &$known): ?string {
            if (null === $prescriptionId) {
                return null;
            }

            if (null === $known) {
                $known = [];
                $ids   = [];

                foreach ($entries as $entry) {
                    if ($entry['item'] instanceof ClinicPortalRequest && null !== $entry['item']->prescriptionId) {
                        $ids[] = $entry['item']->prescriptionId;
                    }
                }

                $summaries = $this->queryBus->ask(new ListPrescriptionPortalSummaries($clinicId, array_values(array_unique($ids))));
                \assert(\is_array($summaries));

                foreach ($summaries as $summary) {
                    \assert($summary instanceof PrescriptionPortalSummary);
                    $known[$summary->id] = $summary->firstMedication;
                }
            }

            return $known[$prescriptionId] ?? null;
        };
    }

    /**
     * @param list<ClinicPortalRequest>  $requests
     * @param list<ClinicLinkRequest>    $links
     * @param list<ClinicBookingRequest> $bookings
     *
     * @return list<Entry>
     */
    private function entries(array $requests, array $links, array $bookings): array
    {
        $entries = [];

        foreach ($requests as $item) {
            $entries[] = ['id' => $item->id, 'kind' => $item->kind, 'status' => $item->status, 'at' => $item->submittedAt->getTimestamp(), 'urgent' => 'adverse_effects' === $item->petCondition && 'submitted' === $item->status, 'item' => $item];
        }

        foreach ($links as $link) {
            $entries[] = ['id' => $link->decisionId ?? $link->userId, 'kind' => self::LINK, 'status' => self::linkStatus($link), 'at' => $link->requestedAt->getTimestamp(), 'urgent' => false, 'item' => $link];
        }

        foreach ($bookings as $booking) {
            $entries[] = ['id' => $booking->id, 'kind' => self::BOOKING, 'status' => self::bookingStatus($booking), 'at' => $booking->createdAt->getTimestamp(), 'urgent' => false, 'item' => $booking];
        }

        return $entries;
    }

    /**
     * @param \Closure(?string): ?string $medication
     *
     * @return RequestRow
     */
    private function entryRow(ClinicPortalRequest|ClinicLinkRequest|ClinicBookingRequest $item, \Closure $medication): array
    {
        return match (true) {
            $item instanceof ClinicLinkRequest    => $this->linkRow($item),
            $item instanceof ClinicBookingRequest => $this->bookingRow($item),
            default                               => $this->row($item, $medication),
        };
    }

    /**
     * Where a queue entry opens: the page and the sheet.
     *
     * @param Entry $entry
     *
     * @return Ref
     */
    private function ref(array $entry): array
    {
        return match ($entry['kind']) {
            self::LINK    => $entry['item'] instanceof ClinicLinkRequest ? $this->linkRef($entry['item']) : throw new \LogicException('A « Rattachement » entry holds its request.'),
            self::BOOKING => [
                'id'    => $entry['id'],
                'url'   => $this->urls->generate('clinic_portal_booking_requests_show', ['id' => $entry['id']]),
                'sheet' => $this->urls->generate('clinic_portal_booking_request_sheet', ['id' => $entry['id']]),
            ],
            default => [
                'id'    => $entry['id'],
                'url'   => $this->urls->generate('clinic_portal_requests_show', ['id' => $entry['id']]),
                'sheet' => $this->urls->generate('clinic_portal_request_sheet', ['id' => $entry['id']]),
            ],
        };
    }

    /**
     * The species emoji of each change / renewal row, read once for the page.
     *
     * @param list<ClinicPortalRequest> $items
     * @param list<RequestRow>          $rows
     */
    private function speciesOf(string $clinicId, array $items, array &$rows): void
    {
        $clientIds = array_values(array_unique(array_map(static fn (ClinicPortalRequest $item): string => $item->clientId, $items)));

        if ([] === $clientIds) {
            return;
        }

        $summaries = $this->queryBus->ask(new ListAnimalSummariesPerClientIds($clinicId, $clientIds, 50));
        $species   = [];

        foreach (\is_array($summaries) ? $summaries : [] as $pets) {
            foreach (\is_array($pets) ? $pets : [] as $pet) {
                if ($pet instanceof AnimalSummary) {
                    $species[$pet->id] = $pet->species;
                }
            }
        }

        $petIds = [];

        foreach ($items as $item) {
            $petIds[$item->id] = $item->petId;
        }

        foreach ($rows as $i => $row) {
            if (isset($petIds[$row['id']], $species[$petIds[$row['id']]])) {
                $rows[$i]['petEmoji'] = self::SPECIES_EMOJI[$species[$petIds[$row['id']]]] ?? '🐾';
            }
        }
    }

    /**
     * @param \Closure(?string): ?string $medication
     *
     * @return RequestRow
     */
    private function row(ClinicPortalRequest $item, \Closure $medication): array
    {
        $isRenewal = self::RENEWAL === $item->kind;
        $pending   = 'submitted' === $item->status;

        return [
            ...$this->timing($item->submittedAt, $pending),
            'id'      => $item->id,
            'kind'    => $item->kind,
            'type'    => self::TYPES[$item->kind][0] ?? 'modif',
            'short'   => self::TYPES[$item->kind][2] ?? $item->kind,
            'subject' => $isRenewal
                ? $medication($item->prescriptionId) ?? PortalRequestText::label(PortalRequestText::CONDITION, $item->petCondition)
                : $this->field($item->field),
            'note'        => $item->note,
            'petName'     => $item->petName ?? 'Animal inconnu',
            'petEmoji'    => '🐾',
            'clientName'  => $item->clientName ?? 'Client inconnu',
            'isNew'       => false,
            'status'      => $item->status,
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, $item->status),
            'statusClass' => self::STATUS_CLASS[$item->status] ?? 's-clos',
            'resolvedBy'  => $item->resolvedByName,
            'resolvedDay' => null === $item->resolvedAt ? null : $this->dayAt($item->resolvedAt),
            'urgent'      => 'adverse_effects' === $item->petCondition && $pending,
            'condition'   => $isRenewal && null !== $item->petCondition ? [
                'cls'   => self::CONDITION_CLASS[$item->petCondition] ?? '',
                'label' => PortalRequestText::label(PortalRequestText::CONDITION, $item->petCondition),
            ] : null,
            'flags'       => [],
            'unreachable' => $item->clientUnreachable,
            'url'         => $this->urls->generate('clinic_portal_requests_show', ['id' => $item->id]),
            'sheet'       => $this->urls->generate('clinic_portal_request_sheet', ['id' => $item->id]),
        ];
    }

    /**
     * @return RequestRow
     */
    private function linkRow(ClinicLinkRequest $request): array
    {
        $petNames = array_merge(...array_map(static fn (LinkRequestFile $file): array => $file->petNames, $request->files));
        $status   = self::linkStatus($request);

        return [
            ...$this->timing($request->requestedAt, !$request->isDecided()),
            ...$this->linkRef($request),
            'kind'        => self::LINK,
            'type'        => 'ratt',
            'short'       => 'Rattachement',
            'subject'     => $request->hasNameMismatch() ? 'Nom différent' : 'Fiche correspondante',
            'note'        => null,
            'petName'     => [] === $petNames ? '—' : implode(', ', $petNames),
            'petEmoji'    => '🐾',
            'clientName'  => implode(' · ', array_map(static fn (LinkRequestFile $file): string => $file->name() ?? 'Client inconnu', $request->files)),
            'isNew'       => false,
            'status'      => $status,
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, $status),
            'statusClass' => self::STATUS_CLASS[$status],
            'resolvedBy'  => $request->decidedByName,
            'resolvedDay' => null === $request->decidedAt ? null : $this->dayAt($request->decidedAt),
            'urgent'      => false,
            'condition'   => null,
            'flags'       => !$request->isDecided() && $request->hasFileLinkedElsewhere() ? ['Déjà relié à un autre compte Portal'] : [],
            'unreachable' => false,
        ];
    }

    /**
     * @return RequestRow
     */
    private function bookingRow(ClinicBookingRequest $request): array
    {
        $status  = self::bookingStatus($request);
        $pending = 'submitted' === $status;

        return [
            ...$this->timing($request->createdAt, $pending),
            'id'          => $request->id,
            'kind'        => self::BOOKING,
            'type'        => 'nouveau',
            'short'       => 'Nouveau client',
            'subject'     => [] === $request->matches ? 'RDV ' . $this->long($request->startAt) : 'Fiche existante possible',
            'note'        => null,
            'petName'     => $request->petName ?? '—',
            'petEmoji'    => self::SPECIES_EMOJI[$request->petSpecies ?? ''] ?? '🐾',
            'clientName'  => $request->requesterName() ?? 'Profil Portal sans nom',
            'isNew'       => true,
            'status'      => $status,
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, $status),
            'statusClass' => self::STATUS_CLASS[$status],
            'resolvedBy'  => $pending ? null : self::bookingResolver($request),
            'resolvedDay' => null === $request->resolvedAt ? null : $this->dayAt($request->resolvedAt),
            'urgent'      => false,
            'condition'   => null,
            'flags'       => !$pending ? [] : array_values(array_filter([
                $request->hasNameMismatch() ? 'Nom différent' : null,
                $request->hasFileLinkedElsewhere() ? 'Déjà relié à un autre compte Portal' : null,
            ])),
            'unreachable' => false,
            'url'         => $this->urls->generate('clinic_portal_booking_requests_show', ['id' => $request->id]),
            'sheet'       => $this->urls->generate('clinic_portal_booking_request_sheet', ['id' => $request->id]),
        ];
    }

    /**
     * Where a « Rattachement » opens: the account's pending request, or the decision once answered.
     *
     * @return Ref
     */
    private function linkRef(ClinicLinkRequest $request): array
    {
        return null === $request->decisionId ? [
            'id'    => $request->userId,
            'url'   => $this->urls->generate('clinic_portal_link_requests_show', ['userId' => $request->userId]),
            'sheet' => $this->urls->generate('clinic_portal_link_request_sheet', ['userId' => $request->userId]),
        ] : [
            'id'    => $request->decisionId,
            'url'   => $this->urls->generate('clinic_portal_link_decisions_show', ['id' => $request->decisionId]),
            'sheet' => $this->urls->generate('clinic_portal_link_decision_sheet', ['id' => $request->decisionId]),
        ];
    }

    /** The register state of a « Rattachement »: waiting, confirmed (done) or rejected. */
    private static function linkStatus(ClinicLinkRequest $request): string
    {
        return match ($request->outcome) {
            null        => 'submitted',
            'confirmed' => 'done',
            default     => 'rejected',
        };
    }

    /** The register state of a « Nouveau client »: waiting, confirmed (done) or declined (rejected). */
    private static function bookingStatus(ClinicBookingRequest $request): string
    {
        return match ($request->status) {
            'pending'   => 'submitted',
            'confirmed' => 'done',
            default     => 'rejected',
        };
    }

    /**
     * Who answered a « Nouveau client »: the staff member from « Demandes »,
     * else what ended it (the agenda, the expiry, the account deletion).
     */
    private static function bookingResolver(ClinicBookingRequest $request): ?string
    {
        if ('pending' === $request->status) {
            return null;
        }

        return $request->resolvedByName ?? ('confirmed' === $request->status ? 'Agenda' : self::SYSTEM[$request->declineReason ?? ''] ?? 'Système');
    }

    /**
     * « Aujourd'hui · 08:51 » and, while it waits, « il y a 2 h » / « depuis 2 jours ».
     *
     * @return array{day: string, age: ?string, late: bool, at: int}
     */
    private function timing(\DateTimeImmutable $at, bool $pending): array
    {
        $minutes = $this->minutesSince($at->getTimestamp(), $this->clock->now());

        return [
            'day' => $this->day($at, false),
            'age' => $pending ? match (true) {
                $minutes < 60   => 'il y a ' . max(1, $minutes) . ' min',
                $minutes < 1440 => 'il y a ' . intdiv($minutes, 60) . ' h',
                default         => 'depuis ' . SheetText::plural(intdiv($minutes, 1440), 'jour', 'jours'),
            } : null,
            'late' => $pending && $minutes >= self::LATE_MINUTES,
            'at'   => $at->getTimestamp(),
        ];
    }

    private function minutesSince(int $at, \DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $at, 60));
    }

    /** « Aujourd'hui · 08:51 », « Hier · 18:20 », « 18 août · 20:37 » (lower case inside a sentence). */
    private function day(\DateTimeImmutable $at, bool $inSentence): string
    {
        $today = $this->clock->now()->setTimezone($at->getTimezone())->format('Y-m-d');
        $day   = match ($at->format('Y-m-d')) {
            $today                                                                                => $inSentence ? 'aujourd’hui' : 'Aujourd’hui',
            new \DateTimeImmutable($today, $at->getTimezone())->modify('-1 day')->format('Y-m-d') => $inSentence ? 'hier' : 'Hier',
            default                                                                               => ($inSentence ? 'le ' : '') . SheetText::date($at, substr($today, 0, 4) === $at->format('Y') ? 'd MMM' : 'd MMM y'),
        };

        return $day . ' · ' . $at->format('H:i');
    }

    /** « 20 août · 11:02 ». */
    private function dayAt(\DateTimeImmutable $at): string
    {
        return SheetText::date($at, 'd MMM') . ' · ' . $at->format('H:i');
    }

    /** « lun. 24 août 09:30 ». */
    private function long(\DateTimeImmutable $at): string
    {
        return SheetText::date($at, 'EEE d MMM') . ' ' . $at->format('H:i');
    }

    private function when(\DateTimeImmutable $at): string
    {
        return SheetText::date($at, 'd MMM y') . ' à ' . $at->format('H:i');
    }
}
