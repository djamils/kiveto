<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CancelAppointment;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointmentHandler;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private CancelAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->handler = new CancelAppointmentHandler(
            $this->appointmentRepository,
            $this->clock,
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

        ($this->handler)(new CancelAppointment($appointmentId->toString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment('11111111-1111-1111-1111-111111111111'));
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

        ($this->handler)(new CancelAppointment($appointmentId->toString()));
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
