<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Infrastructure\Adapter\Scheduling\QueryBusBookingMotifs;
use App\Context\ClientPortal\Infrastructure\Adapter\Scheduling\QueryBusOwnerAppointments;
use App\Context\Scheduling\Application\Query\GetOwnerAppointment\GetOwnerAppointment;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\AppointmentListScope;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\ListAppointmentsForOwners;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\PortalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\CareBookingItem;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\ListCareBookingsForAnimals;
use App\Shared\Application\Pagination\CursorPage;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class BookingQueryBusAdaptersTest extends TestCase
{
    public function testAPageOfAppointmentsIsSchedulings(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC'));
        $bus = new AnsweringQueryBus([ListAppointmentsForOwners::class => new CursorPage([self::item()], 'next')]);

        $page = new QueryBusOwnerAppointments($bus)->page('clinic-1', ['c1'], ['a1'], AppointmentScope::PAST, 'cursor', 5, $now);

        self::assertSame('next', $page->nextCursor);
        self::assertCount(1, $page->items);
        self::assertSame(self::expected(), self::fields($page->items[0]));
        $query = $bus->asked[0];
        self::assertInstanceOf(ListAppointmentsForOwners::class, $query);
        self::assertSame(['clinic-1', ['c1'], ['a1'], AppointmentListScope::PAST, 'cursor', 5, $now], [$query->clinicId, $query->ownerIds, $query->animalIds, $query->scope, $query->cursor, $query->limit, $query->nowUtc]);

        $empty = new QueryBusOwnerAppointments(new AnsweringQueryBus())->page('clinic-1', ['c1'], [], AppointmentScope::UPCOMING, null, null, $now);
        self::assertSame([[], null], [$empty->items, $empty->nextCursor]);
    }

    public function testOneAppointmentIsSchedulings(): void
    {
        $bus = new AnsweringQueryBus([GetOwnerAppointment::class => self::item()]);

        $appointment = new QueryBusOwnerAppointments($bus)->find('clinic-1', 'ap1');

        self::assertNotNull($appointment);
        self::assertSame(self::expected(), self::fields($appointment));
        $query = $bus->asked[0];
        self::assertInstanceOf(GetOwnerAppointment::class, $query);
        self::assertSame(['clinic-1', 'ap1'], [$query->clinicId, $query->appointmentId]);

        self::assertNull(new QueryBusOwnerAppointments(new AnsweringQueryBus())->find('clinic-1', 'ap1'));
    }

    public function testTheCareBookingsAreSchedulings(): void
    {
        $from = new \DateTimeImmutable('2026-10-29 00:00:00', new \DateTimeZone('Europe/Paris'));
        $at   = new \DateTimeImmutable('2026-11-04 09:30:00', new \DateTimeZone('UTC'));
        $bus  = new AnsweringQueryBus([ListCareBookingsForAnimals::class => [new CareBookingItem('a1', $at, 'vaccination')]]);

        $bookings = new QueryBusOwnerAppointments($bus)->careBookings('clinic-1', ['a1', 'a2'], $from);

        self::assertCount(1, $bookings);
        self::assertSame(['a1', $at, 'vaccination'], [$bookings[0]->animalId, $bookings[0]->startsAtUtc, $bookings[0]->preventiveCareKind]);
        $query = $bus->asked[0];
        self::assertInstanceOf(ListCareBookingsForAnimals::class, $query);
        self::assertSame(['clinic-1', ['a1', 'a2'], $from], [$query->clinicId, $query->animalIds, $query->fromUtc]);

        self::assertSame([], new QueryBusOwnerAppointments($bus)->careBookings('clinic-1', [], $from));
        self::assertCount(1, $bus->asked, 'no animal, no query');
        self::assertSame([], new QueryBusOwnerAppointments(new AnsweringQueryBus())->careBookings('clinic-1', ['a1'], $from));
    }

    public function testTheOnlineMotifsAreTheOpenUnarchivedOnes(): void
    {
        $motif = static fn (string $id, bool $online, ?\DateTimeImmutable $archivedAt): BookingMotifView => new BookingMotifView($id, 'Motif ' . $id, 'Description', 15, $online, 'auto', ['consultation'], ['u1'], 'vaccination', false, 0, $archivedAt);
        $bus   = new AnsweringQueryBus([ListBookingMotifs::class => [
            $motif('m1', true, null),
            $motif('m2', false, null),
            $motif('m3', true, new \DateTimeImmutable('2026-10-01')),
        ]]);

        $motifs = new QueryBusBookingMotifs($bus)->onlineMotifs('clinic-1');

        self::assertCount(1, $motifs);
        self::assertSame(['m1', 'Motif m1', 'Description', 15, 'vaccination'], [$motifs[0]->id, $motifs[0]->label, $motifs[0]->description, $motifs[0]->durationMin, $motifs[0]->preventiveCareKind]);
        $query = $bus->asked[0];
        self::assertInstanceOf(ListBookingMotifs::class, $query);
        self::assertSame(['clinic-1', false], [$query->clinicId, $query->includeArchived]);

        self::assertSame([], new QueryBusBookingMotifs(new AnsweringQueryBus())->onlineMotifs('clinic-1'));
    }

    private static function item(): PortalAppointmentItem
    {
        $utc = new \DateTimeZone('UTC');

        return new PortalAppointmentItem(
            'ap1',
            'c1',
            'a1',
            'u1',
            new \DateTimeImmutable('2026-11-05 09:00:00', $utc),
            30,
            'pending_clinic_confirmation',
            true,
            true,
            'portal',
            null,
            'm1',
            'Rappel vaccin',
            'vaccination',
            new \DateTimeImmutable('2026-10-20 08:00:00', $utc),
            new \DateTimeImmutable('2026-11-04 09:00:00', $utc),
        );
    }

    /**
     * @return list<bool|int|string|null>
     */
    private static function expected(): array
    {
        return ['ap1', 'c1', 'a1', 'u1', '2026-11-05 09:00', 30, 'pending_clinic_confirmation', true, true, 'portal', 'm1', 'Rappel vaccin', 'vaccination', '2026-10-20 08:00', '2026-11-04 09:00'];
    }

    /**
     * @return list<bool|int|string|null>
     */
    private static function fields(OwnerAppointment $a): array
    {
        return [$a->id, $a->ownerId, $a->animalId, $a->practitionerUserId, $a->startsAtUtc->format('Y-m-d H:i'), $a->durationMinutes, $a->status, $a->occupiesSlot, $a->isPending, $a->source, $a->motifId, $a->motifLabel, $a->preventiveCareKind, $a->createdAtUtc->format('Y-m-d H:i'), $a->cancellableUntil?->format('Y-m-d H:i')];
    }
}
