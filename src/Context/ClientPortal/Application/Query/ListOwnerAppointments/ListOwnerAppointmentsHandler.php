<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerAppointments;

use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListOwnerAppointmentsHandler
{
    public function __construct(
        private OwnerPets $pets,
        private OwnerAppointmentsInterface $appointments,
        private PortalAppointments $views,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return CursorPage<PortalAppointment>
     */
    public function __invoke(ListOwnerAppointments $query): CursorPage
    {
        $clientIds = $this->pets->clientIds($query->userId, $query->clinicId);
        $pets      = $this->pets->visible($query->userId, $query->clinicId);
        $page      = $this->appointments->page(
            $query->clinicId,
            $clientIds,
            array_map(static fn (PortalPet $pet): string => $pet->id, $pets),
            $query->scope,
            $query->cursor,
            $query->limit,
            $this->clock->now(),
        );

        return new CursorPage($this->views->views($query->clinicId, $page->items, $pets), $page->nextCursor);
    }
}
