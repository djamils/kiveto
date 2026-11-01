<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\OnlineBooking;

use App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment\RescheduleOnlineAppointment;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\CancellationWindowClosedException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\OnlineBookingWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RescheduleOnlineAppointmentHandlerTest extends TestCase
{
    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
    }

    public function testMovesToAnotherPractitionerOfTheMotifKeepingItsStatus(): void
    {
        // Wednesday 09:00 in Paris, booked on 10 April
        $this->world->existing('2026-04-15 07:00');

        $id = $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00'), W::LAMBERT));

        self::assertSame(W::BOOKED, $id);
        $appointment = $this->world->stored(W::BOOKED);
        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertSame(W::LAMBERT, $appointment->practitionerAssignee()->userId()->toString());
        self::assertSame('2026-04-16T08:00:00+00:00', $appointment->timeSlot()->startsAtUtc()->format('c'));
        // Both lock sets (P1)
        self::assertSame([W::ROUSSEAU . '|2026-04-15', W::LAMBERT . '|2026-04-16'], $this->world->recordingLock->locked);
        // The moved appointment is left out of the busy intervals, as a current read
        self::assertSame([W::BOOKED, true], [$this->world->sources->appointmentReads[0][2], $this->world->sources->appointmentReads[0][3]]);
        self::assertSame([AppointmentRescheduled::class, AppointmentPractitionerAssigneeChanged::class], $this->world->eventClasses());
        self::assertSame([[
            'clinicId'      => W::CLINIC,
            'userIds'       => [W::ROUSSEAU, W::LAMBERT],
            'appointmentId' => W::BOOKED,
            'date'          => '2026-04-16',
            'title'         => 'RDV modifié en ligne · Milo',
            'detail'        => 'Sophie Dubois · jeudi 16/04 à 10h00',
            'key'           => 'online-booking-changed:' . W::BOOKED . ':' . W::LAMBERT . ':' . W::utc('2026-04-16 08:00')->getTimestamp(),
        ]], $this->world->bell->onlineChanges);
    }

    public function testAPendingRequestGetsANewExpiry(): void
    {
        $this->world->existing('2026-04-15 08:00', status: AppointmentStatus::PENDING_CLINIC_CONFIRMATION, motifId: W::MANUAL, createdAt: '2026-04-12 08:00');

        $this->world->reschedule()(self::command(W::paris('2026-04-15 11:00')));

        $appointment = $this->world->stored(W::BOOKED);
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
        // min(created + 48 h, start − 2 h)
        self::assertSame('2026-04-14T08:00:00+00:00', $appointment->pendingExpiresAt()?->format('c'));
        self::assertSame([AppointmentRescheduled::class], $this->world->eventClasses());
        self::assertSame([W::ROUSSEAU], $this->world->bell->onlineChanges[0]['userIds']);
    }

    public function testAPendingRequestOnlyMovesAfterTheManualLeadTime(): void
    {
        // An auto motif would accept Tuesday 09:30, 25 h 30 from now; a pending request waits 26 h
        $this->world->existing('2026-04-15 08:00', status: AppointmentStatus::PENDING_CLINIC_CONFIRMATION, createdAt: '2026-04-12 08:00');

        $this->expectException(SlotTakenException::class);

        $this->world->reschedule()(self::command(W::paris('2026-04-14 09:30')));
    }

    public function testTheSameSlotWithTheSamePractitionerChangesNothing(): void
    {
        $this->world->existing('2026-04-15 07:00');

        self::assertSame(W::BOOKED, $this->world->reschedule()(self::command(W::paris('2026-04-15 09:00'))));
        self::assertSame([], $this->world->recordingLock->locked);
        self::assertSame([], $this->world->events->events);
        self::assertSame([], $this->world->bell->onlineChanges);
    }

    public function testATakenSlotIsRefused(): void
    {
        $this->world->existing('2026-04-15 07:00');
        $this->world->sources->appointments[] = BusyInterval::appointment(W::utc('2026-04-16 08:00'), W::utc('2026-04-16 08:30'));

        $this->expectException(SlotTakenException::class);

        $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00')));
    }

    public function testTheWindowIsClosed20HoursBeforeTheStart(): void
    {
        // Created 3 days ago, starting in 20 h, 24 h window (AC 48)
        $this->world->existing('2026-04-14 02:00');

        try {
            $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00')));
            self::fail('The window is closed.');
        } catch (CancellationWindowClosedException $e) {
            self::assertSame(W::BOOKED, $e->appointmentId);
            self::assertSame('2026-04-13T02:00:00+00:00', $e->cancellableUntil?->format('c'));
        }

        self::assertSame([], $this->world->recordingLock->locked);
    }

    public function testAMoveExactlyAtCancellableUntilIsAllowed(): void
    {
        $this->world->existing('2026-04-14 08:00');
        $this->world->clock->at('2026-04-13 08:00');

        $this->world->reschedule()(self::command(W::paris('2026-04-14 11:00')));

        self::assertSame('2026-04-14T09:00:00+00:00', $this->world->stored(W::BOOKED)->timeSlot()->startsAtUtc()->format('c'));
    }

    public function testAStartedAppointmentNoLongerMoves(): void
    {
        $this->world->existing('2026-04-13 05:30', createdAt: '2026-04-13 05:50');

        $this->expectException(CancellationWindowClosedException::class);

        $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00')));
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('provideARefusedMoveCases')]
    public function testARefusedMove(
        AppointmentStatus $status,
        AppointmentSource $source,
        ?string $motifId,
        string $practitionerId,
        bool $enabled,
        string $expected,
    ): void {
        $this->world->existing('2026-04-15 07:00', status: $status, source: $source, motifId: $motifId);
        $this->world->settings->save(W::settings(enabled: $enabled));

        try {
            $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00'), $practitionerId));
            self::fail('The move should be refused.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }

        self::assertSame([], $this->world->events->events);
    }

    /**
     * @return iterable<string, array{AppointmentStatus, AppointmentSource, ?string, string, bool, class-string<\Throwable>}>
     */
    public static function provideARefusedMoveCases(): iterable
    {
        $planned = AppointmentStatus::PLANNED;
        $portal  = AppointmentSource::PORTAL;

        yield 'declined' => [AppointmentStatus::DECLINED, $portal, W::AUTO, W::ROUSSEAU, true, AppointmentNotModifiableException::class];
        yield 'completed' => [AppointmentStatus::COMPLETED, $portal, W::AUTO, W::ROUSSEAU, true, AppointmentNotModifiableException::class];
        yield 'clinic-created' => [$planned, AppointmentSource::CLINIC, null, W::ROUSSEAU, true, BookingNotOpenException::class];
        yield 'portal without motif' => [$planned, $portal, null, W::ROUSSEAU, true, BookingNotOpenException::class];
        yield 'settings disabled' => [$planned, $portal, W::AUTO, W::ROUSSEAU, false, BookingNotOpenException::class];
        yield 'motif no longer online' => [$planned, $portal, W::OFFLINE, W::ROUSSEAU, true, BookingNotOpenException::class];
        yield 'motif archived' => [$planned, $portal, W::ARCHIVED, W::ROUSSEAU, true, BookingNotOpenException::class];
        yield 'practitioner not on the motif (AC 49)' => [$planned, $portal, W::AUTO, W::MARTIN, true, OnlineBookingNotFoundException::class];
        yield 'malformed practitioner id' => [$planned, $portal, W::AUTO, 'nope', true, OnlineBookingNotFoundException::class];
    }

    #[DataProvider('provideAnUnknownAppointmentIsNotFoundCases')]
    public function testAnUnknownAppointmentIsNotFound(string $clinicId, string $appointmentId): void
    {
        $this->world->existing('2026-04-15 07:00');

        try {
            $this->world->reschedule()(new RescheduleOnlineAppointment($clinicId, $appointmentId, W::ROUSSEAU, W::paris('2026-04-16 10:00')));
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

    public function testADeclineCommittedWhileWaitingForTheLockWins(): void
    {
        $this->world->existing('2026-04-15 07:00', status: AppointmentStatus::PENDING_CLINIC_CONFIRMATION, createdAt: '2026-04-12 08:00');
        $this->world->dayLock = new class($this->world->appointments) implements PractitionerDayLockInterface {
            public function __construct(private readonly InMemoryAppointments $appointments)
            {
            }

            public function lock(ClinicId $clinicId, array $keys): void
            {
                $appointment = $this->appointments->findById(AppointmentId::fromString(W::BOOKED));
                \assert(null !== $appointment);
                $appointment->decline(DeclineReason::EXPIRED);
            }

            public function purgeBefore(string $localDate): int
            {
                return 0;
            }
        };

        $this->expectException(AppointmentNotModifiableException::class);

        $this->world->reschedule()(self::command(W::paris('2026-04-16 10:00')));
    }

    private static function command(\DateTimeImmutable $startsAt, string $practitionerId = W::ROUSSEAU): RescheduleOnlineAppointment
    {
        return new RescheduleOnlineAppointment(W::CLINIC, W::BOOKED, $practitionerId, $startsAt);
    }
}
