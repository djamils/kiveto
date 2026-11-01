<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestReadModelInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Service\RequestCursor;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Scoped in SQL to the account's confirmed links of the clinic. A pet
 * archived or deceased since closes its open requests at read time (no
 * Animal event announces it).
 */
#[AsMessageHandler]
final readonly class ListOwnerPortalRequestsHandler
{
    public function __construct(
        private PortalRequestReadModelInterface $requests,
        private PetDirectoryInterface $pets,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * @return CursorPage<OwnerPortalRequest>
     */
    public function __invoke(ListOwnerPortalRequests $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : RequestCursor::position($query->cursor);
        $rows  = $this->requests->ownerPage($query->userId, $query->clinicId, $after, $limit + 1);
        $next  = null;

        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $last = $rows[$limit - 1];
            $next = RequestCursor::after($last->submittedAt, $last->id);
        }

        if ([] === $rows) {
            return new CursorPage([], null);
        }

        $zone   = new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone);
        $states = $this->pets->statesOf($query->clinicId, array_values(array_unique(array_map(static fn (PortalRequestRecord $r): string => $r->petId, $rows))));

        return new CursorPage(array_map(static fn (PortalRequestRecord $row): OwnerPortalRequest => new OwnerPortalRequest(
            $row->id,
            $row->kind,
            $row->petId,
            $row->field,
            $row->proposedValue,
            $row->prescriptionId,
            RequestStatus::SUBMITTED->value === $row->status && null !== ($states[$row->petId] ?? null)?->closureState() ? RequestStatus::CLOSED->value : $row->status,
            $row->submittedAt->setTimezone($zone),
            $row->resolvedAt?->setTimezone($zone),
            $row->resolutionMessage,
            $row->resultingPrescriptionId,
        ), $rows), $next);
    }
}
