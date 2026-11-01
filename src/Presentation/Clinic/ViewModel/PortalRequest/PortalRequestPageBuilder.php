<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetPortalRequest\PortalRequestDetails;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingClientMatch;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\BookingMatchPet;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\LinkRequestFile;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ClinicPortalRequest;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ListClinicPortalRequests;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListItemView;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptions;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The « Demandes Portal » queue and the detail of one request, from the
 * ClientPortal queries. Owner free text (notes, proposed values, Portal
 * profile names) is handed as plain strings: the templates escape it,
 * nothing is marked safe. « Rattachement » requests (pending links) only
 * exist while they wait, and so do « Nouveau client » requests (first
 * appointments through the directory): they head the first page of the
 * « À traiter » and « Toutes » views.
 *
 * @phpstan-type RequestRow array{
 *     id: string, kind: string, kindLabel: string, subject: string, petName: string, clientName: string,
 *     note: ?string, status: string, statusLabel: string, submittedAt: string, resolvedBy: ?string,
 *     urgent: bool, unreachable: bool, url: string, badges: list<string>
 * }
 */
final readonly class PortalRequestPageBuilder
{
    public const array STATUSES = ['submitted', 'done', 'rejected'];

    public const array KINDS = ['pet_change', 'prescription_renewal', self::LINK, self::BOOKING];

    public const string LINK = 'client_link';

    public const string BOOKING = 'booking_request';

    /** Recent issued prescriptions read to find the ones written for a renewal. */
    private const int CANDIDATES = 25;

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private PortalRequestQueue $queue,
    ) {
    }

    /**
     * One page of the queue.
     *
     * @return array{rows: list<RequestRow>, status: ?string, kind: ?string, next: ?string}
     */
    public function list(string $clinicId, ?string $status, ?string $kind, ?string $cursor): array
    {
        $status  = \in_array($status, self::STATUSES, true) ? $status : null;
        $kind    = \in_array($kind, self::KINDS, true) ? $kind : null;
        $waiting = null === $cursor && \in_array($status, [null, 'submitted'], true);
        $links   = $waiting && \in_array($kind, [null, self::LINK], true)
            ? array_map($this->linkRow(...), $this->queue->links($clinicId))
            : [];
        $bookings = $waiting && \in_array($kind, [null, self::BOOKING], true)
            ? array_map($this->bookingRow(...), $this->queue->bookings($clinicId))
            : [];

        if (self::LINK === $kind || self::BOOKING === $kind) {
            return ['rows' => [...$bookings, ...$links], 'status' => $status, 'kind' => $kind, 'next' => null];
        }

        try {
            $page = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, $status, $kind, $cursor));
        } catch (\InvalidArgumentException) {
            // A tampered cursor starts the queue over.
            $page = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, $status, $kind));
        }
        \assert($page instanceof CursorPage);

        $rows = [...$bookings, ...$links];

        foreach ($page->items as $item) {
            \assert($item instanceof ClinicPortalRequest);
            $rows[] = $this->row($item);
        }

        return [
            'rows'   => $rows,
            'status' => $status,
            'kind'   => $kind,
            'next'   => $page->nextCursor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PortalRequestDetails $request, string $clinicId): array
    {
        $isRenewal = 'prescription_renewal' === $request->kind;
        $weight    = 'weight' === $request->field ? $request->normalisedValue['weightGrams'] ?? null : null;
        $measured  = 'weight' === $request->field ? $request->normalisedValue['measuredOn'] ?? null : null;

        return [
            'id'                => $request->id,
            'kind'              => $request->kind,
            'kindLabel'         => PortalRequestText::label(PortalRequestText::KIND, $request->kind),
            'isRenewal'         => $isRenewal,
            'field'             => $request->field,
            'fieldLabel'        => PortalRequestText::label(PortalRequestText::FIELD, $request->field),
            'conditionLabel'    => PortalRequestText::label(PortalRequestText::CONDITION, $request->petCondition),
            'urgent'            => 'adverse_effects' === $request->petCondition,
            'petName'           => $request->petName ?? 'Animal inconnu',
            'clientName'        => $request->clientName ?? 'Client inconnu',
            'petUrl'            => $this->urls->generate('clinic_animals_view', ['id' => $request->petId]),
            'clientUrl'         => $this->urls->generate('clinic_clients_view', ['id' => $request->clientId]),
            'note'              => $request->note,
            'status'            => $request->status,
            'statusLabel'       => PortalRequestText::label(PortalRequestText::STATUS, $request->status),
            'closedReason'      => null === $request->closedReason ? null : PortalRequestText::label(PortalRequestText::CLOSED_REASON, $request->closedReason),
            'submittedAt'       => $this->when($request->submittedAt),
            'resolvedAt'        => null === $request->resolvedAt ? null : $this->when($request->resolvedAt),
            'resolvedBy'        => $request->resolvedByName,
            'resolutionMessage' => $request->resolutionMessage,
            'unreachable'       => $request->clientUnreachable,
            'values'            => $isRenewal ? null : [
                'atSubmit' => PortalRequestText::value($request->field, $request->currentValueAtSubmit),
                'current'  => PortalRequestText::value($request->field, $request->currentValue),
                'proposed' => \is_int($weight) ? PortalRequestText::kilograms($weight) : $request->proposedValue,
            ],
            'measuredOn' => \is_string($measured) ? SheetText::date(new \DateTimeImmutable($measured), 'd MMMM y') : null,
            // One click to the animal sheet, its weight form filled in: staff still record it.
            'weighUrl' => \is_int($weight) && 'submitted' === $request->status ? $this->urls->generate('clinic_animals_view', array_filter([
                'id'       => $request->petId,
                'pesee'    => str_replace('.', ',', (string) ($weight / 1000)),
                'pesee_le' => \is_string($measured) ? $measured : null,
            ])) : null,
            'prescriptionUrl'          => null === $request->prescriptionId ? null : $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $request->prescriptionId]),
            'resultingPrescriptionUrl' => null === $request->resultingPrescriptionId ? null : $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $request->resultingPrescriptionId]),
            'candidates'               => $isRenewal && 'submitted' === $request->status ? $this->candidates($clinicId, $request) : [],
        ];
    }

    /**
     * A « Rattachement » request: the Portal profile name next to each
     * file's name and animals, its badges.
     *
     * @return array<string, mixed>
     */
    public function linkDetail(ClinicLinkRequest $request): array
    {
        return [
            'userId'      => $request->userId,
            'kindLabel'   => PortalRequestText::label(PortalRequestText::KIND, self::LINK),
            'ownerName'   => $request->ownerName() ?? 'Profil Portal sans nom',
            'submittedAt' => $this->when($request->requestedAt),
            'files'       => array_map(fn (LinkRequestFile $file): array => [
                'clientId'        => $file->clientId,
                'name'            => $file->name() ?? 'Client inconnu',
                'url'             => $this->urls->generate('clinic_clients_view', ['id' => $file->clientId]),
                'petNames'        => $file->petNames,
                'mismatch'        => $file->nameMismatch,
                'linkedElsewhere' => $file->linkedToAnotherAccount,
            ], $request->files),
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

        return [
            'id'          => $request->id,
            'kindLabel'   => PortalRequestText::label(PortalRequestText::KIND, self::BOOKING),
            'ownerName'   => $request->requesterName() ?? 'Profil Portal sans nom',
            'phone'       => $request->requesterPhone,
            'petName'     => $petName,
            'petSpecies'  => PortalRequestText::label(PortalRequestText::SPECIES, $request->petSpecies),
            'motif'       => $request->motif,
            'slot'        => $this->when($request->startAt),
            'submittedAt' => $this->when($request->createdAt),
            'pending'     => 'pending' === $request->status,
            'status'      => $request->status,
            'statusLabel' => match ($request->status) {
                'pending'   => 'À traiter',
                'confirmed' => 'Confirmée',
                default     => PortalRequestText::label(PortalRequestText::DECLINE_REASON, $request->declineReason),
            },
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
                    // Suggested when it looks like the new pet (same name and species)
                    'same' => null !== $request->petName && $pet->species === $request->petSpecies && mb_strtolower($pet->name) === mb_strtolower($request->petName),
                ], $match->pets),
            ], $request->matches),
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
        )));
        \assert($page instanceof PrescriptionListItemView);

        $candidates = [];

        foreach ($page->items as $row) {
            if (null === $row->issuedAt || $row->issuedAt <= $submitted) {
                continue;
            }

            $details = $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $row->id));

            if (!$details instanceof PrescriptionDetails || $details->patientAnimalId !== $request->petId) {
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
     * @return RequestRow
     */
    private function row(ClinicPortalRequest $item): array
    {
        return [
            'id'        => $item->id,
            'kind'      => $item->kind,
            'kindLabel' => PortalRequestText::label(PortalRequestText::KIND, $item->kind),
            'subject'   => 'pet_change' === $item->kind
                ? PortalRequestText::label(PortalRequestText::FIELD, $item->field)
                : PortalRequestText::label(PortalRequestText::CONDITION, $item->petCondition),
            'petName'     => $item->petName ?? 'Animal inconnu',
            'clientName'  => $item->clientName ?? 'Client inconnu',
            'note'        => $item->note,
            'status'      => $item->status,
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, $item->status),
            'submittedAt' => $this->when($item->submittedAt),
            'resolvedBy'  => $item->resolvedByName,
            'urgent'      => 'adverse_effects' === $item->petCondition && 'submitted' === $item->status,
            'unreachable' => $item->clientUnreachable,
            'url'         => $this->urls->generate('clinic_portal_requests_show', ['id' => $item->id]),
            'badges'      => [],
        ];
    }

    /**
     * @return RequestRow
     */
    private function linkRow(ClinicLinkRequest $request): array
    {
        $petNames = array_merge(...array_map(static fn (LinkRequestFile $file): array => $file->petNames, $request->files));

        return [
            'id'          => $request->userId,
            'kind'        => self::LINK,
            'kindLabel'   => PortalRequestText::label(PortalRequestText::KIND, self::LINK),
            'subject'     => 'Compte Portal : ' . ($request->ownerName() ?? 'sans nom'),
            'petName'     => [] === $petNames ? '—' : implode(', ', $petNames),
            'clientName'  => implode(' · ', array_map(static fn (LinkRequestFile $file): string => $file->name() ?? 'Client inconnu', $request->files)),
            'note'        => null,
            'status'      => 'submitted',
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, 'submitted'),
            'submittedAt' => $this->when($request->requestedAt),
            'resolvedBy'  => null,
            'urgent'      => false,
            'unreachable' => false,
            'url'         => $this->urls->generate('clinic_portal_link_requests_show', ['userId' => $request->userId]),
            'badges'      => array_values(array_filter([
                $request->hasNameMismatch() ? 'Nom différent' : null,
                $request->hasFileLinkedElsewhere() ? 'Déjà relié à un autre compte Portal' : null,
            ])),
        ];
    }

    /**
     * @return RequestRow
     */
    private function bookingRow(ClinicBookingRequest $request): array
    {
        return [
            'id'          => $request->id,
            'kind'        => self::BOOKING,
            'kindLabel'   => PortalRequestText::label(PortalRequestText::KIND, self::BOOKING),
            'subject'     => $request->motif . ' · ' . $this->when($request->startAt),
            'petName'     => $request->petName ?? '—',
            'clientName'  => 'Compte Portal : ' . ($request->requesterName() ?? 'sans nom'),
            'note'        => null,
            'status'      => 'submitted',
            'statusLabel' => PortalRequestText::label(PortalRequestText::STATUS, 'submitted'),
            'submittedAt' => $this->when($request->createdAt),
            'resolvedBy'  => null,
            'urgent'      => false,
            'unreachable' => false,
            'url'         => $this->urls->generate('clinic_portal_booking_requests_show', ['id' => $request->id]),
            'badges'      => array_values(array_filter([
                [] === $request->matches ? 'Aucun dossier avec ce mobile' : null,
                $request->hasNameMismatch() ? 'Nom différent' : null,
                $request->hasFileLinkedElsewhere() ? 'Déjà relié à un autre compte Portal' : null,
            ])),
        ];
    }

    private function when(\DateTimeImmutable $at): string
    {
        return SheetText::date($at, 'd MMM y') . ' à ' . $at->format('H:i');
    }
}
