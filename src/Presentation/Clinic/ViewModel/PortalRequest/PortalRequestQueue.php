<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ListClinicBookingRequests;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ListClinicLinkRequests;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ClinicPortalRequest;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ListClinicPortalRequests;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;

/**
 * The « Demandes Portal » still waiting for an answer, read page after page
 * from ClientPortal: the dashboard tasks and the navigation badge count them,
 * « Rattachement » requests (accounts waiting for a link) and « Nouveau
 * client » requests (first appointments through the directory) included.
 * Questionnaires are never queue items.
 */
final readonly class PortalRequestQueue
{
    /** Pages read at most: beyond, the queue is long enough for any badge. */
    private const int MAX_PAGES = 10;

    private const int PAGE_SIZE = 50;

    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * Every submitted request of the clinic, newest first.
     *
     * @return list<ClinicPortalRequest>
     */
    public function open(string $clinicId): array
    {
        $requests = [];
        $cursor   = null;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $result = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, 'submitted', null, $cursor, self::PAGE_SIZE));
            \assert($result instanceof CursorPage);

            foreach ($result->items as $item) {
                \assert($item instanceof ClinicPortalRequest);
                $requests[] = $item;
            }

            $cursor = $result->nextCursor;

            if (null === $cursor) {
                break;
            }
        }

        return $requests;
    }

    /**
     * The « Rattachement » requests (pending links), oldest first.
     *
     * @return list<ClinicLinkRequest>
     */
    public function links(string $clinicId): array
    {
        $requests = $this->queryBus->ask(new ListClinicLinkRequests($clinicId));
        \assert(\is_array($requests));

        return array_values(array_filter($requests, static fn (mixed $request): bool => $request instanceof ClinicLinkRequest));
    }

    /**
     * The « Nouveau client » requests (pending booking requests), oldest first.
     *
     * @return list<ClinicBookingRequest>
     */
    public function bookings(string $clinicId): array
    {
        $requests = $this->queryBus->ask(new ListClinicBookingRequests($clinicId));
        \assert(\is_array($requests));

        return array_values(array_filter($requests, static fn (mixed $request): bool => $request instanceof ClinicBookingRequest));
    }

    /** How many requests wait for an answer, of every type. */
    public function count(string $clinicId): int
    {
        return \count($this->open($clinicId)) + \count($this->links($clinicId)) + \count($this->bookings($clinicId));
    }
}
