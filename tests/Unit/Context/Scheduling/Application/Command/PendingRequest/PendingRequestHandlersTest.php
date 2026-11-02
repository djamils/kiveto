<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\PendingRequest;

use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointment;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointmentHandler;
use App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment\ConfirmNewClientAppointment;
use App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment\ConfirmNewClientAppointmentHandler;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointmentHandler;
use App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment\DeclineExpiredPendingAppointment;
use App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment\DeclineExpiredPendingAppointmentHandler;
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointments;
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointmentsHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentOwnerChanged;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\NewClientRequestException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\CapturingJobs;
use App\Tests\Unit\Context\Scheduling\Support\FakeNewClientConfirmation;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\TestCase;

/**
 * Confirm, decline and auto-decline of a pending online request (S4e, P2):
 * each takes the practitioner-day lock of the slot, then reads the row for
 * update before deciding (AC 35, 36, 37, 144).
 */
final class PendingRequestHandlersTest extends TestCase
{
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-0000000c0009';

    private InMemoryAppointments $appointments;
    private RecordingDayLock $dayLock;
    private CapturingEventBus $events;
    private MovableClock $clock;
    private FakeNewClientConfirmation $newClients;

    protected function setUp(): void
    {
        $this->newClients   = new FakeNewClientConfirmation();
        $this->appointments = new InMemoryAppointments();
        $this->dayLock      = new RecordingDayLock();
        $this->events       = new CapturingEventBus();
        // Requested on 13/10 at 08:42 UTC, expiring on 15/10 at 08:42
        $this->clock = new MovableClock(Appointments::utc('2026-10-14 09:00'));
    }

    public function testTheClinicConfirmsAPendingRequestUnderTheLocks(): void
    {
        $this->appointments->save(Appointments::pending());

        $this->confirm();

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame(AppointmentStatus::PLANNED, $appointment?->status());
        self::assertFalse($appointment->confirmedSilently());
        // 09:30 Paris on 22 October: that day of Dr Rousseau, then the row
        self::assertSame([Appointments::ROUSSEAU . '|2026-10-22'], $this->dayLock->locked);
        self::assertSame(Appointments::CLINIC, $this->dayLock->clinicId);
        self::assertSame([Appointments::ID], $this->appointments->lockedForUpdate);
        self::assertCount(1, $this->events->events);
        self::assertInstanceOf(AppointmentConfirmed::class, $this->events->events[0]);
    }

    public function testAConfirmAfterTheExpiryIsRefused(): void
    {
        // AC 144: « Demande expirée. », even before the scan declined it
        $this->appointments->save(Appointments::pending());
        $this->clock->at('2026-10-15 08:42');

        try {
            $this->confirm();
            self::fail('An expired request cannot be confirmed.');
        } catch (PendingAppointmentExpiredException $e) {
            self::assertSame('Demande expirée.', $e->getMessage());
        }

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame([], $this->events->events);
    }

    public function testARequestAlreadyHandledCannotBeConfirmedNorDeclined(): void
    {
        $this->appointments->save(Appointments::planned());

        try {
            $this->confirm();
            self::fail('A planned appointment was already handled.');
        } catch (AppointmentAlreadyHandledException $e) {
            self::assertSame('Ce RDV a déjà été traité.', $e->getMessage());
        }

        $this->expectException(AppointmentAlreadyHandledException::class);
        $this->decline();
    }

    public function testAnAppointmentOfAnotherClinicOrUnknownIsMissing(): void
    {
        $this->appointments->save(Appointments::pending());

        foreach ([fn () => $this->confirm(self::OTHER_CLINIC), fn () => $this->decline(self::OTHER_CLINIC)] as $write) {
            try {
                $write();
                self::fail('Another clinic\'s appointment is missing.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('does not exist', $e->getMessage());
            }
        }

        self::assertSame([], $this->dayLock->locked);
        self::assertSame([], $this->appointments->lockedForUpdate);
    }

    public function testTheClinicDeclinesAPendingRequest(): void
    {
        // AC 36: DECLINED, the slot is free
        $this->appointments->save(Appointments::pending());
        // Even past its expiry, a request may still be declined
        $this->clock->at('2026-10-16 08:00');

        $this->decline();

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame(AppointmentStatus::DECLINED, $appointment?->status());
        self::assertFalse($appointment->status()->occupiesSlot());
        self::assertSame([Appointments::ROUSSEAU . '|2026-10-22'], $this->dayLock->locked);
        self::assertCount(1, $this->events->events);
        $event = $this->events->events[0];
        self::assertInstanceOf(AppointmentDeclined::class, $event);
        self::assertSame([DeclineReason::DECLINED->value, true], [$event->reason(), $event->notifyClient()]);
    }

    public function testTheScanQueuesOneDeclinePerExpiredRequest(): void
    {
        // AC 37: created 49 h ago, or starting in 1 h 50 → expired; created 1 h ago starting in 3 days → untouched
        $this->clock->at('2026-10-15 09:42');
        $this->appointments->save(Appointments::pending(id: '01960000-0000-7000-8000-0000000a0011', expiresAtUtc: '2026-10-15 08:42'));
        $this->appointments->save(Appointments::pending(startsAtUtc: '2026-10-15 11:32', expiresAtUtc: '2026-10-15 09:32', id: '01960000-0000-7000-8000-0000000a0012'));
        $this->appointments->save(Appointments::pending(startsAtUtc: '2026-10-18 09:00', expiresAtUtc: '2026-10-17 08:42', id: '01960000-0000-7000-8000-0000000a0013'));
        $this->appointments->save(Appointments::planned(id: '01960000-0000-7000-8000-0000000a0014'));
        $jobs = new CapturingJobs();

        $queued = new ScanExpiredPendingAppointmentsHandler($this->appointments, $jobs, $this->clock)(new ScanExpiredPendingAppointments());

        self::assertSame(2, $queued);
        self::assertSame(['01960000-0000-7000-8000-0000000a0011', '01960000-0000-7000-8000-0000000a0012'], $jobs->expiredDeclines);
    }

    public function testAnExpiredRequestIsDeclinedAsExpired(): void
    {
        $this->appointments->save(Appointments::pending());
        $this->clock->at('2026-10-15 08:45');

        $this->declineExpired();

        self::assertSame(AppointmentStatus::DECLINED, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame([Appointments::ROUSSEAU . '|2026-10-22'], $this->dayLock->locked);
        $event = $this->events->events[0] ?? null;
        self::assertInstanceOf(AppointmentDeclined::class, $event);
        self::assertSame(DeclineReason::EXPIRED->value, $event->reason());
    }

    public function testTheAutoDeclineReChecksStatusAndExpiryUnderTheLock(): void
    {
        // Confirmed meanwhile, moved later (not expired any more), or gone: left alone
        $this->appointments->save(Appointments::pending());
        $this->declineExpired();

        $this->clock->at('2026-10-15 08:45');
        $this->appointments->save(Appointments::planned());
        $this->declineExpired();

        new DeclineExpiredPendingAppointmentHandler($this->loader(), $this->appointments, $this->clock, new DomainEventPublisher($this->events))(
            new DeclineExpiredPendingAppointment('01960000-0000-7000-8000-0000000a0099'),
        );

        self::assertSame(AppointmentStatus::PLANNED, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame([], $this->events->events);
    }

    /** S7-01: the agenda's « Confirmer » of a new client's request goes through ClientPortal, before any lock here. */
    public function testTheAgendaConfirmsANewClientsRequestThroughClientPortal(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));
        $this->newClients->onConfirm = function (string $clinicId, string $appointmentId, bool $silent): void {
            // ClientPortal locks its request, then dispatches ConfirmNewClientAppointment
            self::assertSame([], $this->dayLock->locked);
            $this->confirmNewClient($clinicId, $silent);
        };

        $this->confirm();

        self::assertSame([[Appointments::CLINIC, Appointments::ID, Appointments::ANIMAL, false]], $this->newClients->calls);
        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, false, 'client-new'], [$appointment?->status(), $appointment?->confirmedSilently(), $appointment?->ownerId()]);
        self::assertSame([AppointmentOwnerChanged::class, AppointmentConfirmed::class], array_map(static fn (object $e): string => $e::class, $this->events->events));
    }

    /** S7d, AC 266: without a pending booking request, the agenda still refuses a new client's request; nothing changes. */
    public function testANewClientsRequestWithoutBookingRequestIsRefused(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));

        try {
            $this->confirm();
            self::fail('A new client\'s request is refused in the agenda.');
        } catch (NewClientRequestException $e) {
            self::assertSame('À traiter dans Demandes Portal.', $e->getMessage());
        }

        self::assertCount(1, $this->newClients->calls);
        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame([], $this->events->events);
    }

    public function testAKnownClientsRequestOrAnotherClinicsNeverReachesClientPortal(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));

        try {
            $this->confirm(self::OTHER_CLINIC);
            self::fail('Another clinic\'s appointment is missing.');
        } catch (\InvalidArgumentException) {
        }

        $this->appointments->save(Appointments::pending());
        $this->confirm();

        self::assertSame([], $this->newClients->calls);
    }

    public function testANewClientOnSiteIsConfirmedSilentlyEvenPastItsExpiry(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));
        $this->clock->at('2026-10-16 08:00');

        $this->confirmNewClient(silent: true);

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, true, 'client-new', 'pet-new'], [$appointment?->status(), $appointment?->confirmedSilently(), $appointment?->ownerId(), $appointment?->animalId()]);
    }

    public function testStaffConfirmANewClientsRequestForTheFileAndAnimalTheyChose(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));

        $this->confirmNewClient();

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, 'client-new', 'pet-new'], [$appointment?->status(), $appointment?->ownerId(), $appointment?->animalId()]);
        self::assertSame([AppointmentOwnerChanged::class, AppointmentConfirmed::class], array_map(static fn (object $e): string => $e::class, $this->events->events));
        self::assertSame([Appointments::ID], $this->appointments->lockedForUpdate);
    }

    public function testANewClientsRequestHandledOrExpiredIsRefused(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));
        $this->clock->at('2026-10-15 08:42');

        try {
            $this->confirmNewClient();
            self::fail('Expired.');
        } catch (PendingAppointmentExpiredException) {
        }

        $this->clock->at('2026-10-14 09:00');
        $this->decline();

        try {
            $this->confirmNewClient();
            self::fail('Declined first.');
        } catch (AppointmentAlreadyHandledException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->confirmNewClient(self::OTHER_CLINIC);
    }

    private function confirmNewClient(string $clinicId = Appointments::CLINIC, bool $silent = false): void
    {
        new ConfirmNewClientAppointmentHandler($this->loader(), $this->appointments, $this->clock, new DomainEventPublisher($this->events))(
            new ConfirmNewClientAppointment(Appointments::ID, $clinicId, 'client-new', 'pet-new', $silent),
        );
    }

    private function confirm(string $clinicId = Appointments::CLINIC): void
    {
        new ConfirmAppointmentHandler($this->loader(), $this->appointments, $this->clock, new DomainEventPublisher($this->events), $this->newClients)(
            new ConfirmAppointment(Appointments::ID, $clinicId),
        );
    }

    private function decline(string $clinicId = Appointments::CLINIC): void
    {
        new DeclineAppointmentHandler($this->loader(), $this->appointments, new DomainEventPublisher($this->events))(
            new DeclineAppointment(Appointments::ID, $clinicId),
        );
    }

    private function declineExpired(): void
    {
        new DeclineExpiredPendingAppointmentHandler($this->loader(), $this->appointments, $this->clock, new DomainEventPublisher($this->events))(
            new DeclineExpiredPendingAppointment(Appointments::ID),
        );
    }

    private function loader(): PendingAppointmentLoader
    {
        return new PendingAppointmentLoader($this->appointments, new ParisTimezone(), $this->dayLock);
    }
}
