<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\OnlineBooking;

use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Tests\Unit\Context\Scheduling\Support\OnlineBookingWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookOnlineAppointmentHandlerTest extends TestCase
{
    private const string NEW_ID = '01960000-0000-7000-8000-0000000a0201';

    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
    }

    public function testAnAutoMotifConfirmsTheBookingAtOnce(): void
    {
        // Tuesday 10:00 in Paris, given with its offset
        $id = $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 10:00'), createdPetId: W::ANIMAL));

        self::assertSame(self::NEW_ID, $id);
        $appointment = $this->world->stored($id);
        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertSame(AppointmentSource::PORTAL, $appointment->source());
        self::assertSame(W::AUTO, $appointment->bookingMotifId());
        // The agenda chip and the messages keep reading the motif's label
        self::assertSame('Consultation', $appointment->reason());
        self::assertSame(W::OWNER, $appointment->ownerId());
        self::assertSame(W::ANIMAL, $appointment->animalId());
        self::assertSame(W::ANIMAL, $appointment->createdPetId());
        self::assertNull($appointment->pendingExpiresAt());
        // Stored in UTC
        self::assertSame('2026-04-14T08:00:00+00:00', $appointment->timeSlot()->startsAtUtc()->format('c'));
        self::assertSame(30, $appointment->timeSlot()->durationMinutes());
        self::assertSame('2026-04-13T06:00:00+00:00', $appointment->createdAt()->format('c'));
        self::assertSame([W::ROUSSEAU . '|2026-04-14'], $this->world->recordingLock->locked);
        self::assertSame([AppointmentScheduled::class], $this->world->eventClasses());
        // Availability was recomputed as a current read, under the lock
        self::assertSame([['2026-04-14', '2026-04-14', true]], $this->world->sources->blockReads);
        self::assertTrue($this->world->sources->appointmentReads[0][3]);
    }

    public function testAManualMotifWaitsForTheClinicWithAnExpiry(): void
    {
        $id = $this->world->book()(self::command(W::MANUAL, W::utc('2026-04-14 09:00')));

        $appointment = $this->world->stored($id);
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
        self::assertSame('Vaccination', $appointment->reason());
        self::assertSame(W::MANUAL, $appointment->bookingMotifId());
        self::assertNull($appointment->createdPetId());
        // min(created + 48 h, start − 2 h): later than its creation (AC 147)
        self::assertSame('2026-04-14T07:00:00+00:00', $appointment->pendingExpiresAt()?->format('c'));
        self::assertSame([AppointmentRequested::class], $this->world->eventClasses());
    }

    public function testAManualMotifOffersNothingBeforeTheManualLeadTime(): void
    {
        // Tuesday 09:30 in Paris is 25 h 30 from now: before the manual lead time of 26 h
        $this->expectException(SlotTakenException::class);

        $this->world->book()(self::command(W::MANUAL, W::paris('2026-04-14 09:30')));
    }

    public function testARetryOfACommittedBookingAnswersTheSameAppointment(): void
    {
        $this->world->existing('2026-04-14 08:00', createdAt: '2026-04-12 07:00');
        $this->world->sources->appointments[] = BusyInterval::appointment(W::utc('2026-04-14 08:00'), W::utc('2026-04-14 08:30'));

        $id = $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 10:00')));

        self::assertSame(W::BOOKED, $id);
        self::assertSame([], $this->world->events->events);
    }

    public function testABookingOlderThan24HoursIsNoRetry(): void
    {
        $this->world->existing('2026-04-14 08:00', createdAt: '2026-04-12 05:59');
        $this->world->sources->appointments[] = BusyInterval::appointment(W::utc('2026-04-14 08:00'), W::utc('2026-04-14 08:30'));

        try {
            $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 10:00')));
            self::fail('The slot is taken.');
        } catch (SlotTakenException $e) {
            self::assertSame(SlotTakenException::UNAVAILABLE, $e->reason);
            self::assertStringContainsString('2026-04-14T10:00:00+02:00', $e->getMessage());
        }
    }

    public function testAnotherOwnerGetsSlotTaken(): void
    {
        $this->world->existing('2026-04-14 08:00', ownerId: W::LEFEVRE);
        $this->world->sources->appointments[] = BusyInterval::appointment(W::utc('2026-04-14 08:00'), W::utc('2026-04-14 08:30'));

        $this->expectException(SlotTakenException::class);

        $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 10:00')));
    }

    public function testAStartOffTheGridIsNotOffered(): void
    {
        $this->expectException(SlotTakenException::class);

        $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 10:05')));
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('provideARefusedRequestCases')]
    public function testARefusedRequest(string $motifId, string $practitionerId, bool $enabled, string $expected, ?string $subject): void
    {
        $this->world->settings->save(W::settings(enabled: $enabled));

        try {
            $this->world->book()(self::command($motifId, W::paris('2026-04-14 10:00'), $practitionerId));
            self::fail('The booking should be refused.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);

            if ($e instanceof OnlineBookingNotFoundException) {
                self::assertSame($subject, $e->subject);
            }
        }

        self::assertSame([], $this->world->recordingLock->locked);
        self::assertSame([], $this->world->events->events);
    }

    /**
     * @return iterable<string, array{string, string, bool, class-string<\Throwable>, ?string}>
     */
    public static function provideARefusedRequestCases(): iterable
    {
        yield 'settings disabled' => [W::AUTO, W::ROUSSEAU, false, BookingNotOpenException::class, null];
        yield 'disabled wins over an unknown motif' => ['01960000-0000-7000-8000-00000000b0ff', W::ROUSSEAU, false, BookingNotOpenException::class, null];
        yield 'motif not online' => [W::OFFLINE, W::ROUSSEAU, true, BookingNotOpenException::class, null];
        yield 'practitioner not on the motif' => [W::AUTO, W::MARTIN, true, BookingNotOpenException::class, null];
        yield 'unknown motif' => ['01960000-0000-7000-8000-00000000b0ff', W::ROUSSEAU, true, OnlineBookingNotFoundException::class, OnlineBookingNotFoundException::MOTIF];
        yield 'archived motif' => [W::ARCHIVED, W::ROUSSEAU, true, OnlineBookingNotFoundException::class, OnlineBookingNotFoundException::MOTIF];
        yield 'malformed motif id' => ['nope', W::ROUSSEAU, true, OnlineBookingNotFoundException::class, OnlineBookingNotFoundException::MOTIF];
        yield 'malformed practitioner id' => [W::AUTO, 'nope', true, OnlineBookingNotFoundException::class, OnlineBookingNotFoundException::PRACTITIONER];
    }

    private static function command(string $motifId, \DateTimeImmutable $startsAt, string $practitionerId = W::ROUSSEAU, ?string $createdPetId = null): BookOnlineAppointment
    {
        return new BookOnlineAppointment(W::CLINIC, $motifId, $practitionerId, $startsAt, W::OWNER, W::ANIMAL, $createdPetId);
    }
}
