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

    /** Pages of history the register reads at most (the oldest answered requests beyond are not listed). */
    private const int MAX_HISTORY_PAGES = 40;

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
        return $this->read($clinicId, 'submitted', self::MAX_PAGES);
    }

    /**
     * Every change and renewal request of the clinic, whatever its state
     * (closed ones aside), newest first: the « Demandes » register filters,
     * counts and pages them.
     *
     * @return list<ClinicPortalRequest>
     */
    public function all(string $clinicId): array
    {
        return $this->read($clinicId, null, self::MAX_HISTORY_PAGES);
    }

    /**
     * The « Rattachement » requests: the pending links, oldest first, or the
     * clinic's answers (`$decided`), the latest first.
     *
     * @return list<ClinicLinkRequest>
     */
    public function links(string $clinicId, bool $decided = false): array
    {
        $requests = $this->queryBus->ask(new ListClinicLinkRequests($clinicId, $decided));
        \assert(\is_array($requests));

        return array_values(array_filter($requests, static fn (mixed $request): bool => $request instanceof ClinicLinkRequest));
    }

    /**
     * The « Nouveau client » requests: the pending ones, oldest first, or the
     * confirmed and declined ones (`$decided`), the latest answered first.
     *
     * @return list<ClinicBookingRequest>
     */
    public function bookings(string $clinicId, bool $decided = false): array
    {
        $requests = $this->queryBus->ask(new ListClinicBookingRequests($clinicId, $decided));
        \assert(\is_array($requests));

        return array_values(array_filter($requests, static fn (mixed $request): bool => $request instanceof ClinicBookingRequest));
    }

    /** How many requests wait for an answer, of every type. */
    public function count(string $clinicId): int
    {
        return \count($this->open($clinicId)) + \count($this->links($clinicId)) + \count($this->bookings($clinicId));
    }

    /**
     * @return list<ClinicPortalRequest>
     */
    private function read(string $clinicId, ?string $status, int $maxPages): array
    {
        $requests = [];
        $cursor   = null;

        for ($page = 0; $page < $maxPages; ++$page) {
            $result = $this->queryBus->ask(new ListClinicPortalRequests($clinicId, $status, null, $cursor, self::PAGE_SIZE));
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
}
