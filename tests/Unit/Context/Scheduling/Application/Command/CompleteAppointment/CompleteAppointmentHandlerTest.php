<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CompleteAppointment;

use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointmentHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Application\Service\SilentPendingConfirmation;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\FakeNewClientConfirmation;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CompleteAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $repository;
    private CompleteAppointmentHandler $handler;
    private CapturingEventBus $events;
    private RecordingDayLock $dayLock;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->events     = new CapturingEventBus();
        $this->dayLock    = new RecordingDayLock();
        $this->handler    = new CompleteAppointmentHandler(
            new SilentPendingConfirmation($this->repository, new PendingAppointmentLoader($this->repository, new ParisTimezone(), $this->dayLock), new FakeNewClientConfirmation()),
            $this->repository,
            new DomainEventPublisher($this->events),
        );
    }

    public function testCompleteAppointmentSuccessfully(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn ($id): bool => $id instanceof AppointmentId
                && $id->equals($appointmentId)))
            ->willReturn($appointment)
        ;

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Appointment $a): bool => AppointmentStatus::COMPLETED === $a->status()))
        ;

        ($this->handler)(new CompleteAppointment($appointmentId->toString()));

        self::assertSame([AppointmentScheduled::class, AppointmentCompleted::class], array_map(static fn (object $e): string => $e::class, $this->events->events));
    }

    public function testAPendingRequestIsConfirmedSilentlyAndItsEventsArePublished(): void
    {
        // AC 140: the client is on site — PLANNED then COMPLETED, Confirmed{silent} published
        $pending = Appointments::pending();
        $this->repository->expects(self::once())->method('findById')->willReturn($pending);
        $this->repository->expects(self::once())->method('findByIdForUpdate')->willReturn($pending);

        ($this->handler)(new CompleteAppointment(Appointments::ID));

        self::assertSame(AppointmentStatus::COMPLETED, $pending->status());
        self::assertTrue($pending->confirmedSilently());
        $confirmed = $this->events->events[0] ?? null;
        self::assertInstanceOf(AppointmentConfirmed::class, $confirmed);
        self::assertTrue($confirmed->silent());
        self::assertInstanceOf(AppointmentCompleted::class, $this->events->events[1] ?? null);
        // The silent confirmation path takes the practitioner-day lock
        self::assertSame([Appointments::ROUSSEAU . '|2026-10-22'], $this->dayLock->locked);
    }

    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CompleteAppointment('11111111-1111-1111-1111-111111111111'));
    }

    private function makeAppointment(AppointmentId $id): Appointment
    {
        return Appointment::schedule(
            id: $id,
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
