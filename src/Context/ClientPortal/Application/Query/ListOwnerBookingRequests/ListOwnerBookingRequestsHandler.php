<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Service\RequestCursor;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Keyset on `(created_at, id)`, newest first; one clinic read per clinic of the page. */
#[AsMessageHandler]
final readonly class ListOwnerBookingRequestsHandler
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * @return CursorPage<OwnerBookingRequest>
     */
    public function __invoke(ListOwnerBookingRequests $query): CursorPage
    {
        $limit = Cursor::clampLimit($query->limit);
        $after = null === $query->cursor ? null : RequestCursor::position($query->cursor);
        $rows  = $this->requests->findPageByUser($query->userId, $after?->submittedAt, $after?->id, $limit + 1);
        $next  = null;

        if (\count($rows) > $limit) {
            $rows = \array_slice($rows, 0, $limit);
            $last = $rows[$limit - 1];
            $next = RequestCursor::after($last->createdAt(), $last->id()->toString());
        }

        /** @var array<string, ClinicLabel> $clinics */
        $clinics = [];

        foreach ($rows as $row) {
            $clinics[$row->clinicId()] ??= $this->clinics->clinicOf($row->clinicId());
        }

        return new CursorPage(array_map(static fn (BookingRequest $row): OwnerBookingRequest => OwnerBookingRequest::of($row, $clinics[$row->clinicId()]), $rows), $next);
    }
}
