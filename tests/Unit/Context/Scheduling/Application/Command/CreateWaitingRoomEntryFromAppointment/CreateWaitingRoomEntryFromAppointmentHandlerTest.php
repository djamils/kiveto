<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment;

use App\Context\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment\CreateWaitingRoomEntryFromAppointment as CreateEntryCmd; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment\CreateWaitingRoomEntryFromAppointmentHandler as CreateEntryHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Scheduling\Application\Exception\WaitingRoomEntryAlreadyExistsException;
use App\Context\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateWaitingRoomEntryFromAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private WaitingRoomEntryRepositoryInterface&MockObject $entryRepository;
    private WaitingRoomReadRepositoryInterface&MockObject $entryReadRepository;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private CreateEntryHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->entryRepository       = $this->createMock(WaitingRoomEntryRepositoryInterface::class);
        $this->entryReadRepository   = $this->createMock(WaitingRoomReadRepositoryInterface::class);
        $this->uuidGenerator         = $this->createMock(UuidGeneratorInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->handler = new CreateEntryHandler(
            $this->appointmentRepository,
            $this->entryRepository,
            $this->entryReadRepository,
            $this->uuidGenerator,
            $this->clock,
        );
    }

    public function testCreateEntryFromAppointmentSuccessfully(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->entryReadRepository->expects(self::once())
            ->method('hasActiveEntryForAppointment')
            ->willReturn(false)
        ;
        $this->uuidGenerator->expects(self::once())
            ->method('generate')
            ->willReturn('33333333-3333-3333-3333-333333333333')
        ;
        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-04-10 08:55:00'))
        ;
        $this->entryRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(WaitingRoomEntry::class))
        ;

        $entryId = ($this->handler)(new CreateEntryCmd(
            appointmentId: $appointmentId->toString(),
            arrivalMode: 'STANDARD',
            priority: 0,
        ));

        self::assertSame('33333333-3333-3333-3333-333333333333', $entryId);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CreateEntryCmd(
            appointmentId: '11111111-1111-1111-1111-111111111111',
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenActiveEntryAlreadyExists(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $this->appointmentRepository->expects(self::once())
            ->method('findById')
            ->willReturn($this->makeAppointment($appointmentId))
        ;
        $this->entryReadRepository->expects(self::once())
            ->method('hasActiveEntryForAppointment')
            ->willReturn(true)
        ;

        $this->expectException(WaitingRoomEntryAlreadyExistsException::class);

        ($this->handler)(new CreateEntryCmd(
            appointmentId: $appointmentId->toString(),
        ));
    }

    private function makeAppointment(AppointmentId $id): Appointment
    {
        return Appointment::schedule(
            id: $id,
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            ownerId: null,
            animalId: null,
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
