<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CancelAppointment;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointmentHandler;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelAppointmentHandlerTest extends TestCase
{
    private const string CLINIC = '22222222-2222-2222-2222-222222222222';

    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private CancelAppointmentHandler $handler;
    private CapturingEventBus $events;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->events  = new CapturingEventBus();
        $this->handler = new CancelAppointmentHandler(
            $this->appointmentRepository,
            $this->clock,
            new DomainEventPublisher($this->events),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCancelAppointmentSucceeds(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Appointment $a): bool => AppointmentStatus::CANCELLED === $a->status()))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:55:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC));

        // Published after the save: the scheduling subscribers react to it.
        $cancelled = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentCancelled));
        self::assertCount(1, $cancelled);
        self::assertTrue($cancelled[0]->notifyClient());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheDeathOfTheAnimalCancelsWithoutTellingTheClient(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');

        $this->appointmentRepository->method('findById')->willReturn($this->makeAppointment($appointmentId));
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:55:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC, notifyClient: false));

        $cancelled = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentCancelled));
        self::assertCount(1, $cancelled);
        self::assertFalse($cancelled[0]->notifyClient());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAClientCancelsWithAReason(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC, cancelledBy: 'client', cancellationReason: 'no_longer_needed'));

        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertSame(CancellationActor::CLIENT, $appointment->cancelledBy());
        self::assertSame(CancellationReason::NO_LONGER_NEEDED, $appointment->cancellationReason());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheClinicCancellingAPendingRequestDeclinesIt(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = Appointment::request(
            id: $appointmentId,
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
            ownerId: null,
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable('2026-04-10 07:00:00'),
        );

        $this->appointmentRepository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 06:00:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC));

        self::assertSame(AppointmentStatus::DECLINED, $appointment->status());
        $declined = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentDeclined));
        self::assertCount(1, $declined);
        self::assertSame('clinic_cancelled', $declined[0]->reason());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment('11111111-1111-1111-1111-111111111111', self::CLINIC));
    }

    public function testAppointmentOfAnotherClinicIsReportedAsMissing(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($this->makeAppointment($appointmentId));
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::never())->method('now');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment($appointmentId->toString(), '99999999-9999-9999-9999-999999999999'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentIsPast(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 10:00:00'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible d\'annuler un rendez-vous passé.');

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC));
    }

    private function makeAppointment(AppointmentId $id): Appointment
    {
        return Appointment::schedule(
            id: $id,
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
