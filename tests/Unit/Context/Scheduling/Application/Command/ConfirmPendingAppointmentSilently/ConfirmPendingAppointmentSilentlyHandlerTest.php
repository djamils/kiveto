<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently;

use App\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently\ConfirmPendingAppointmentSilently;
use App\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently\ConfirmPendingAppointmentSilentlyHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Application\Service\SilentPendingConfirmation;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\FakeNewClientConfirmation;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\TestCase;

/**
 * The client is on site (a consultation starts from the appointment): a
 * pending request is confirmed silently, under the practitioner-day lock
 * and the row lock; anything else is left as it is.
 */
final class ConfirmPendingAppointmentSilentlyHandlerTest extends TestCase
{
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-0000000c0009';

    private InMemoryAppointments $appointments;
    private RecordingDayLock $dayLock;
    private CapturingEventBus $events;
    private FakeNewClientConfirmation $newClients;

    protected function setUp(): void
    {
        $this->newClients   = new FakeNewClientConfirmation();
        $this->appointments = new InMemoryAppointments();
        $this->dayLock      = new RecordingDayLock();
        $this->events       = new CapturingEventBus();
    }

    public function testAPendingRequestIsConfirmedSilentlyUnderTheLocks(): void
    {
        $this->appointments->save(Appointments::pending());

        $this->confirm(Appointments::CLINIC);

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, true, null], [$appointment?->status(), $appointment?->confirmedSilently(), $appointment?->pendingExpiresAt()]);
        self::assertSame([Appointments::ROUSSEAU . '|2026-10-22'], $this->dayLock->locked);
        self::assertSame([Appointments::ID], $this->appointments->lockedForUpdate);

        self::assertCount(1, $this->events->events);
        $confirmed = $this->events->events[0];
        self::assertInstanceOf(AppointmentConfirmed::class, $confirmed);
        self::assertSame([true, 'portal', Appointments::OWNER], [$confirmed->silent(), $confirmed->source(), $confirmed->ownerId()]);
    }

    /** S7-01: a new client on site is confirmed through ClientPortal (client file, animal, Portal link), silently. */
    public function testANewClientsRequestIsConfirmedThroughClientPortalSilently(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));
        $this->newClients->onConfirm = function (string $clinicId, string $appointmentId, bool $silent): void {
            // ClientPortal's ConfirmNewClientAppointment: before any lock here, saved and published there
            self::assertSame([], $this->dayLock->locked);
            $appointment = $this->appointments->findById(AppointmentId::fromString($appointmentId));
            \assert(null !== $appointment);
            $appointment->assignNewClient('client-new', 'pet-new');
            $appointment->confirm($silent);
            $this->appointments->save($appointment);
            $_ = $appointment->pullDomainEvents();
        };

        $this->confirm(null);

        self::assertSame([[Appointments::CLINIC, Appointments::ID, Appointments::ANIMAL, true]], $this->newClients->calls);
        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, true, 'client-new'], [$appointment?->status(), $appointment?->confirmedSilently(), $appointment?->ownerId()]);
        // Read again under the row lock; nothing left to publish here
        self::assertSame([Appointments::ID], $this->appointments->lockedForUpdate);
        self::assertSame([], $this->events->events);
    }

    public function testANewClientsRequestWithoutBookingRequestIsConfirmedSilentlyAsBefore(): void
    {
        $this->appointments->save(Appointments::pending(ownerId: null));

        $this->confirm(Appointments::CLINIC);

        self::assertCount(1, $this->newClients->calls);
        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, null], [$appointment?->status(), $appointment?->ownerId()]);
    }

    public function testWithoutAClinicTheAppointmentIsFoundByItsIdAlone(): void
    {
        $this->appointments->save(Appointments::pending());

        $this->confirm(null);

        self::assertSame(AppointmentStatus::PLANNED, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
    }

    public function testAPlannedAppointmentIsANoOp(): void
    {
        $this->appointments->save(Appointments::planned());

        $this->confirm(Appointments::CLINIC);

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::PLANNED, false], [$appointment?->status(), $appointment?->confirmedSilently()]);
        self::assertSame([], $this->events->events);
        self::assertSame([], $this->dayLock->locked);
    }

    public function testARequestDeclinedMeanwhileIsLeftAsTheWinnerMadeIt(): void
    {
        $seen     = Appointments::pending();
        $declined = Appointments::pending();
        $declined->decline(DeclineReason::EXPIRED);
        $_ = $declined->pullDomainEvents();

        $appointments = $this->createStub(AppointmentRepositoryInterface::class);
        $appointments->method('findById')->willReturn($seen);
        $appointments->method('findByIdForUpdate')->willReturn($declined);

        $this->confirmWith($appointments);

        self::assertSame(AppointmentStatus::DECLINED, $declined->status());
        self::assertSame([], $this->events->events);
    }

    public function testAnUnknownAppointmentOrOneOfAnotherClinicIsReportedAsMissing(): void
    {
        $this->appointments->save(Appointments::pending());

        foreach (['01960000-0000-7000-8000-0000000a0999' => Appointments::CLINIC, Appointments::ID => self::OTHER_CLINIC] as $id => $clinicId) {
            try {
                $this->handler($this->appointments)(new ConfirmPendingAppointmentSilently($id, $clinicId));
                self::fail('An appointment out of reach was confirmed.');
            } catch (\InvalidArgumentException $e) {
                self::assertSame(\sprintf('Appointment with ID "%s" does not exist.', $id), $e->getMessage());
            }
        }

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
    }

    private function confirm(?string $clinicId): void
    {
        $this->handler($this->appointments)(new ConfirmPendingAppointmentSilently(Appointments::ID, $clinicId));
    }

    private function confirmWith(AppointmentRepositoryInterface $appointments): void
    {
        $this->handler($appointments)(new ConfirmPendingAppointmentSilently(Appointments::ID, Appointments::CLINIC));
    }

    private function handler(AppointmentRepositoryInterface $appointments): ConfirmPendingAppointmentSilentlyHandler
    {
        return new ConfirmPendingAppointmentSilentlyHandler(
            new SilentPendingConfirmation($appointments, new PendingAppointmentLoader($appointments, new ParisTimezone(), $this->dayLock), $this->newClients),
            $appointments,
            new DomainEventPublisher($this->events),
        );
    }
}
