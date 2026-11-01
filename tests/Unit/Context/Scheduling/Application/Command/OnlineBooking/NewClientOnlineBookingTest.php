<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\OnlineBooking;

use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\WithdrawPendingOnlineAppointment\WithdrawPendingOnlineAppointment;
use App\Context\Scheduling\Application\Command\WithdrawPendingOnlineAppointment\WithdrawPendingOnlineAppointmentHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\OnlineBookingWorld as W;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use PHPUnit\Framework\TestCase;

/**
 * Slice 7: a new client's request through the directory — no owner nor
 * animal, always pending with the manual lead time, only where the clinic
 * takes new clients online; and its withdrawal at account deletion.
 */
final class NewClientOnlineBookingTest extends TestCase
{
    private const string NO_NEW_MOTIF = '01960000-0000-7000-8000-00000000b005';

    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
    }

    public function testANewClientAlwaysWaitsForTheClinicWithoutOwnerNorAnimal(): void
    {
        // An auto motif: a known client would be confirmed at once
        $id = $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 11:00')));

        $appointment = $this->world->stored($id);
        self::assertSame([AppointmentStatus::PENDING_CLINIC_CONFIRMATION, null, null], [$appointment->status(), $appointment->ownerId(), $appointment->animalId()]);
        self::assertSame('2026-04-14T07:00:00+00:00', $appointment->pendingExpiresAt()?->format('c'));
        self::assertSame([AppointmentRequested::class], $this->world->eventClasses());
    }

    public function testANewClientSeesNothingBeforeTheManualLeadTime(): void
    {
        $this->expectException(SlotTakenException::class);

        // 25 h 30 from now: an auto motif offers it to a known client, never to a new one
        $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 09:30')));
    }

    public function testAClinicThatTakesNoNewClientIsNotOpen(): void
    {
        $this->world->settings->save(new OnlineBookingSettings(ClinicId::fromString(W::CLINIC), true, 15, 120, 26, 60, 24, false));

        $this->expectException(BookingNotOpenException::class);

        $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 11:00')));
    }

    public function testAMotifClosedToNewClientsIsNotOpen(): void
    {
        $this->world->motifs->save(BookingMotif::create(BookingMotifId::fromString(self::NO_NEW_MOTIF), ClinicId::fromString(W::CLINIC), 'Suivi', null, 30, true, ConfirmationMode::AUTO, [PlanningBlockType::CONSULTATION], [UserId::fromString(W::ROUSSEAU)], null, false, 0));

        $this->expectException(BookingNotOpenException::class);

        $this->world->book()(self::command(self::NO_NEW_MOTIF, W::paris('2026-04-14 11:00')));
    }

    public function testANewClientHasNoOwnerAndAKnownOneHasBoth(): void
    {
        foreach ([[W::OWNER, W::ANIMAL, true], [null, null, false], [W::OWNER, null, false]] as [$owner, $animal, $newClient]) {
            try {
                $this->world->book()(new BookOnlineAppointment(W::CLINIC, W::AUTO, W::ROUSSEAU, W::paris('2026-04-14 11:00'), $owner, $animal, newClient: $newClient));
                self::fail('Inconsistent booking.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('new-client booking', $e->getMessage());
            }
        }
    }

    public function testAPendingRequestIsWithdrawnWhateverTheWindowAndOnlyOnce(): void
    {
        $id      = $this->world->book()(self::command(W::AUTO, W::paris('2026-04-14 11:00')));
        $handler = new WithdrawPendingOnlineAppointmentHandler(
            new PendingAppointmentLoader($this->world->appointments, new ParisTimezone(), $this->world->dayLock),
            $this->world->appointments,
            new DomainEventPublisher($this->world->events),
        );
        $this->world->events->events = [];

        $handler(new WithdrawPendingOnlineAppointment(W::CLINIC, $id));
        $handler(new WithdrawPendingOnlineAppointment(W::CLINIC, $id));
        $handler(new WithdrawPendingOnlineAppointment(W::CLINIC, 'nope'));
        $handler(new WithdrawPendingOnlineAppointment(W::OTHER_CLINIC, $id));

        $appointment = $this->world->stored($id);
        self::assertSame([AppointmentStatus::CANCELLED, CancellationActor::CLIENT], [$appointment->status(), $appointment->cancelledBy()]);
        self::assertSame([AppointmentCancelled::class], $this->world->eventClasses());
    }

    private static function command(string $motifId, \DateTimeImmutable $startsAt): BookOnlineAppointment
    {
        return new BookOnlineAppointment(W::CLINIC, $motifId, W::ROUSSEAU, $startsAt, null, null, newClient: true);
    }
}
