<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Scheduling;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Port\CareBooking;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\GetNextAppointmentForAnimals;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\NextAppointmentItem;
use App\Context\Scheduling\Application\Query\GetOwnerAppointment\GetOwnerAppointment;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\AppointmentListScope;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\ListAppointmentsForOwners;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\PortalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\CareBookingItem;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\ListCareBookingsForAnimals;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;

/**
 * Scheduling's owner queries: GetNextAppointmentForAnimals (never an
 * appointment without animal), ListAppointmentsForOwners,
 * GetOwnerAppointment and ListCareBookingsForAnimals.
 */
final readonly class QueryBusOwnerAppointments implements OwnerAppointmentsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment
    {
        if ([] === $animalIds) {
            return null;
        }

        $item = $this->queryBus->ask(new GetNextAppointmentForAnimals($clinicId, $animalIds, $nowUtc));

        if (!$item instanceof NextAppointmentItem) {
            return null;
        }

        return new UpcomingAppointment($item->id, $item->animalId, $item->startsAtUtc, $item->practitionerUserId, $item->category);
    }

    public function page(string $clinicId, array $ownerIds, array $animalIds, AppointmentScope $scope, ?string $cursor, ?int $limit, \DateTimeImmutable $nowUtc): CursorPage
    {
        $page = $this->queryBus->ask(new ListAppointmentsForOwners(
            $clinicId,
            $ownerIds,
            $animalIds,
            AppointmentListScope::from($scope->value),
            $cursor,
            $limit,
            $nowUtc,
        ));

        if (!$page instanceof CursorPage) {
            return new CursorPage([], null);
        }

        $items = [];

        foreach ($page->items as $item) {
            \assert($item instanceof PortalAppointmentItem);
            $items[] = self::appointment($item);
        }

        return new CursorPage($items, $page->nextCursor);
    }

    public function find(string $clinicId, string $appointmentId): ?OwnerAppointment
    {
        $item = $this->queryBus->ask(new GetOwnerAppointment($clinicId, $appointmentId));

        return $item instanceof PortalAppointmentItem ? self::appointment($item) : null;
    }

    public function careBookings(string $clinicId, array $animalIds, \DateTimeImmutable $fromUtc): array
    {
        if ([] === $animalIds) {
            return [];
        }

        $items = $this->queryBus->ask(new ListCareBookingsForAnimals($clinicId, $animalIds, $fromUtc));

        if (!\is_array($items)) {
            return [];
        }

        $bookings = [];

        foreach ($items as $item) {
            \assert($item instanceof CareBookingItem);
            $bookings[] = new CareBooking($item->animalId, $item->startsAtUtc, $item->preventiveCareKind);
        }

        return $bookings;
    }

    private static function appointment(PortalAppointmentItem $item): OwnerAppointment
    {
        return new OwnerAppointment(
            $item->id,
            $item->ownerId,
            $item->animalId,
            $item->practitionerUserId,
            $item->startsAtUtc,
            $item->durationMinutes,
            $item->status,
            $item->occupiesSlot,
            $item->isPending,
            $item->source,
            $item->motifId,
            $item->motifLabel,
            $item->preventiveCareKind,
            $item->createdAtUtc,
            $item->cancellableUntil,
        );
    }
}
