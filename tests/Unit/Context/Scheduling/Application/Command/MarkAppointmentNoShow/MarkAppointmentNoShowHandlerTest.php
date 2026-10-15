<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\MarkAppointmentNoShow;

use App\Context\Scheduling\Application\Command\MarkAppointmentNoShow\MarkAppointmentNoShow;
use App\Context\Scheduling\Application\Command\MarkAppointmentNoShow\MarkAppointmentNoShowHandler;
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

final class MarkAppointmentNoShowHandlerTest extends TestCase
{
    private const string ID     = '11111111-1111-1111-1111-111111111111';
    private const string CLINIC = '22222222-2222-2222-2222-222222222222';

    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private MarkAppointmentNoShowHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->handler = new MarkAppointmentNoShowHandler($this->appointmentRepository, $this->clock);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMarksAStartedAppointmentAsNoShow(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($this->makeAppointment());
        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Appointment $a): bool => AppointmentStatus::NO_SHOW === $a->status()))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:45:00'));

        ($this->handler)(new MarkAppointmentNoShow(self::ID, self::CLINIC));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "' . self::ID . '" does not exist.');

        ($this->handler)(new MarkAppointmentNoShow(self::ID, self::CLINIC));
    }

    public function testAppointmentOfAnotherClinicIsReportedAsMissing(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($this->makeAppointment());
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::never())->method('now');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "' . self::ID . '" does not exist.');

        ($this->handler)(new MarkAppointmentNoShow(self::ID, '99999999-9999-9999-9999-999999999999'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentHasNotStarted(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($this->makeAppointment());
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:30:00'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible de marquer non venu un rendez-vous à venir.');

        ($this->handler)(new MarkAppointmentNoShow(self::ID, self::CLINIC));
    }

    private function makeAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
