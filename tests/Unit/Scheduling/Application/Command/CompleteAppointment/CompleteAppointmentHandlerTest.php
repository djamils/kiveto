<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\Command\CompleteAppointment;

use App\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Scheduling\Application\Command\CompleteAppointment\CompleteAppointmentHandler;
use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CompleteAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $repository;
    private CompleteAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->handler    = new CompleteAppointmentHandler($this->repository);
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
            ownerId: null,
            animalId: null,
            practitionerAssignee: null,
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
