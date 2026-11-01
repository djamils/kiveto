<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\ClientPortal\Application\Query\GetPortalRequest\PortalRequestDetails;
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
 * ClientPortal queries. Owner free text (notes, proposed values) is handed
 * as plain strings: the templates escape it, nothing is marked safe.
 *
 * @phpstan-type RequestRow array{
 *     id: string, kind: string, kindLabel: string, subject: string, petName: string, clientName: string,
 *     note: ?string, status: string, statusLabel: string, submittedAt: string, resolvedBy: ?string,
 *     urgent: bool, unreachable: bool, url: string
 * }
 */
final readonly class PortalRequestPageBuilder
{
    public const array STATUSES = ['submitted', 'done', 'rejected'];

    public const array KINDS = ['pet_change', 'prescription_renewal'];

    /** Recent issued prescriptions read to find the ones written for a renewal. */
    private const int CANDIDATES = 25;

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * One page of the queue.
     *
     * @return array{rows: list<RequestRow>, status: ?string, kind: ?string, next: ?string}
     */
    public function list(string $clinicId, ?string $status, ?string $kind, ?string $cursor): array
    {
        $status = \in_array($status, self::STATUSES, true) ? $status : null;
        $kind   = \in_array($kind, self::KINDS, true) ? $kind : null;

        try {
            $page = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, $status, $kind, $cursor));
        } catch (\InvalidArgumentException) {
            // A tampered cursor starts the queue over.
            $page = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, $status, $kind));
        }
        \assert($page instanceof CursorPage);

        $rows = [];

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
        ];
    }

    private function when(\DateTimeImmutable $at): string
    {
        return SheetText::date($at, 'd MMM y') . ' à ' . $at->format('H:i');
    }
}
