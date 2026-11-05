<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicPortalRequests;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalClientContact;
use App\Context\ClientPortal\Application\Port\PortalRequestReadModelInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Service\RequestCursor;
use App\Context\ClientPortal\Domain\Request\PortalRequestKind;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Closed requests never reach the queue; a submitted request whose pet is
 * archived or deceased since is left out at read time (closed for good
 * when someone tries to answer it), so a page may hold fewer items than
 * the limit. Pet and client names are read once per page. An unknown
 * status or kind lists nothing.
 */
#[AsMessageHandler]
final readonly class ListClinicPortalRequestsHandler
{
    private const array QUEUED = [RequestStatus::SUBMITTED, RequestStatus::DONE, RequestStatus::REJECTED];

    public function __construct(
        private PortalRequestReadModelInterface $requests,
        private PetDirectoryInterface $pets,
        private ClientDirectoryInterface $clients,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * @return CursorPage<ClinicPortalRequest>
     */
    public function __invoke(ListClinicPortalRequests $query): CursorPage
    {
        $statuses = array_map(static fn (RequestStatus $s): string => $s->value, self::QUEUED);
        $after    = null === $query->cursor ? null : RequestCursor::position($query->cursor);

        if ((null !== $query->status && !\in_array($query->status, $statuses, true)) || (null !== $query->kind && null === PortalRequestKind::tryFrom($query->kind))) {
            return new CursorPage([], null);
        }

        $limit = Cursor::clampLimit($query->limit);
        $rows  = $this->requests->clinicPage($query->clinicId, null === $query->status ? $statuses : [$query->status], $query->kind, $after, $limit + 1);
        $next  = null;

        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $last = $rows[$limit - 1];
            $next = RequestCursor::after($last->submittedAt, $last->id);
        }

        if ([] === $rows) {
            return new CursorPage([], null);
        }

        $zone    = new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone);
        $states  = $this->pets->statesOf($query->clinicId, array_values(array_unique(array_map(static fn (PortalRequestRecord $r): string => $r->petId, $rows))));
        $visible = array_filter($rows, static fn (PortalRequestRecord $row): bool => RequestStatus::SUBMITTED->value !== $row->status || null === ($states[$row->petId] ?? null)?->closureState());
        $names   = array_map(
            static fn (PortalClientContact $contact): string => $contact->displayName,
            $this->clients->contactsOf($query->clinicId, array_values(array_unique(array_map(static fn (PortalRequestRecord $r): string => $r->clientId, $visible)))),
        );

        return new CursorPage(array_values(array_map(static fn (PortalRequestRecord $row): ClinicPortalRequest => new ClinicPortalRequest(
            $row->id,
            $row->kind,
            $row->clientId,
            $names[$row->clientId] ?? null,
            $row->petId,
            ($states[$row->petId] ?? null)?->name,
            $row->field,
            $row->proposedValue,
            $row->prescriptionId,
            $row->petCondition,
            $row->note,
            $row->status,
            $row->submittedAt->setTimezone($zone),
            $row->resolvedAt?->setTimezone($zone),
            $row->resolvedByName,
            $row->resolutionMessage,
            $row->resultingPrescriptionId,
            $row->clientUnreachable,
        ), $visible)), $next);
    }
}
