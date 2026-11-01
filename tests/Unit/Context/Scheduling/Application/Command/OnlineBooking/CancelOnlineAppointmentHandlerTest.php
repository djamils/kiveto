<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\OnlineBooking;

use App\Context\Scheduling\Application\Command\CancelOnlineAppointment\CancelOnlineAppointment;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\CancellationWindowClosedException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Tests\Unit\Context\Scheduling\Support\OnlineBookingWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CancelOnlineAppointmentHandlerTest extends TestCase
{
    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
    }

    public function testTheClientCancelsWithinTheWindow(): void
    {
        // Created 3 days ago, starting in 30 h, 24 h window (AC 48)
        $this->world->existing('2026-04-14 12:00');

        $id = $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED, 'unavailable'));

        self::assertSame(W::BOOKED, $id);
        $appointment = $this->world->stored(W::BOOKED);
        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertSame(CancellationActor::CLIENT, $appointment->cancelledBy());
        self::assertSame(CancellationReason::UNAVAILABLE, $appointment->cancellationReason());
        // Locked, then read for update
        self::assertSame([W::ROUSSEAU . '|2026-04-14'], $this->world->recordingLock->locked);
        self::assertSame([W::BOOKED], $this->world->appointments->lockedForUpdate);
        self::assertSame([AppointmentCancelled::class], $this->world->eventClasses());
        self::assertSame([[
            'clinicId'      => W::CLINIC,
            'userIds'       => [W::ROUSSEAU],
            'appointmentId' => W::BOOKED,
            'date'          => '2026-04-14',
            'title'         => 'RDV annulé en ligne · Milo',
            'detail'        => 'Sophie Dubois · mardi 14/04 à 14h00',
            'key'           => 'online-booking-cancelled:' . W::BOOKED,
        ]], $this->world->bell->onlineChanges);
    }

    public function testAPendingRequestIsWithdrawnWithoutReason(): void
    {
        $this->world->existing('2026-04-15 08:00', status: AppointmentStatus::PENDING_CLINIC_CONFIRMATION, ownerId: null);
        $this->world->animals->names = [];

        $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED));

        $appointment = $this->world->stored(W::BOOKED);
        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertNull($appointment->cancellationReason());
        $event = $this->world->events->events[0];
        self::assertInstanceOf(AppointmentCancelled::class, $event);
        self::assertTrue($event->wasPending());
        // Neither the animal nor the owner is known: the bare slot
        self::assertSame('RDV annulé en ligne', $this->world->bell->onlineChanges[0]['title']);
        self::assertSame('mercredi 15/04 à 10h00', $this->world->bell->onlineChanges[0]['detail']);
    }

    public function testCancellingTwiceAnswersTheSameAppointment(): void
    {
        $this->world->existing('2026-04-14 12:00', status: AppointmentStatus::CANCELLED, cancelledBy: CancellationActor::CLIENT);

        self::assertSame(W::BOOKED, $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED, 'other')));
        self::assertSame([], $this->world->events->events);
        self::assertSame([], $this->world->bell->onlineChanges);
    }

    #[DataProvider('provideATerminalAppointmentIsNotModifiableCases')]
    public function testATerminalAppointmentIsNotModifiable(AppointmentStatus $status, ?CancellationActor $cancelledBy): void
    {
        $this->world->existing('2026-04-14 12:00', status: $status, cancelledBy: $cancelledBy);

        $this->expectException(AppointmentNotModifiableException::class);

        $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED));
    }

    /**
     * @return iterable<string, array{AppointmentStatus, ?CancellationActor}>
     */
    public static function provideATerminalAppointmentIsNotModifiableCases(): iterable
    {
        yield 'cancelled by the clinic' => [AppointmentStatus::CANCELLED, CancellationActor::CLINIC];
        yield 'declined' => [AppointmentStatus::DECLINED, null];
        yield 'completed' => [AppointmentStatus::COMPLETED, null];
        yield 'no-show' => [AppointmentStatus::NO_SHOW, null];
    }

    /**
     * AC 48: closed 20 h before; still open at exactly `cancellableUntil`.
     * AC 153: a booking made 2 h before its start can be undone for 15 minutes.
     */
    #[DataProvider('provideTheCancellationWindowCases')]
    public function testTheCancellationWindow(string $startsAt, string $createdAt, string $now, bool $allowed): void
    {
        $this->world->existing($startsAt, createdAt: $createdAt);
        $this->world->clock->at($now);

        try {
            $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED));
            self::assertTrue($allowed, 'The window should be closed.');
        } catch (CancellationWindowClosedException) {
            self::assertFalse($allowed, 'The window should be open.');
        }
    }

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function provideTheCancellationWindowCases(): iterable
    {
        yield 'starts in 20 h' => ['2026-04-14 02:00', '2026-04-10 08:00', W::NOW, false];
        yield 'exactly at cancellableUntil' => ['2026-04-14 06:00', '2026-04-10 08:00', W::NOW, true];
        yield 'a second later' => ['2026-04-14 06:00', '2026-04-10 08:00', '2026-04-13 06:00:01', false];
        yield 'booked 2 h before, 10 min later' => ['2026-04-13 08:00', '2026-04-13 06:00', '2026-04-13 06:10', true];
        yield 'booked 2 h before, 16 min later' => ['2026-04-13 08:00', '2026-04-13 06:00', '2026-04-13 06:16', false];
        yield 'started' => ['2026-04-13 05:55', '2026-04-13 05:50', W::NOW, false];
    }

    #[DataProvider('provideAnUnknownAppointmentIsNotFoundCases')]
    public function testAnUnknownAppointmentIsNotFound(string $clinicId, string $appointmentId): void
    {
        $this->world->existing('2026-04-14 12:00');

        try {
            $this->world->cancel()(new CancelOnlineAppointment($clinicId, $appointmentId));
            self::fail('The appointment should not be found.');
        } catch (OnlineBookingNotFoundException $e) {
            self::assertSame(OnlineBookingNotFoundException::APPOINTMENT, $e->subject);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAnUnknownAppointmentIsNotFoundCases(): iterable
    {
        yield 'unknown' => [W::CLINIC, '01960000-0000-7000-8000-0000000a0fff'];
        yield 'of another clinic' => [W::OTHER_CLINIC, W::BOOKED];
        yield 'malformed' => [W::CLINIC, 'nope'];
    }

    public function testAnUnknownReasonIsRefused(): void
    {
        $this->world->existing('2026-04-14 12:00');

        $this->expectException(\InvalidArgumentException::class);

        $this->world->cancel()(new CancelOnlineAppointment(W::CLINIC, W::BOOKED, 'bored'));
    }
}
