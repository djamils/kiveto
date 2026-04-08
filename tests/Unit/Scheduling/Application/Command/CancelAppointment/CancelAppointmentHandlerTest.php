<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\Command\CancelAppointment;

use App\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Scheduling\Application\Command\CancelAppointment\CancelAppointmentHandler;
use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\WaitingRoomEntryMapper;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private WaitingRoomEntryRepositoryInterface&MockObject $entryRepository;
    private WaitingRoomEntryMapper&MockObject $mapper;
    private EntityManagerInterface&MockObject $entityManager;
    private ClockInterface&MockObject $clock;
    private CancelAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->entryRepository       = $this->createMock(WaitingRoomEntryRepositoryInterface::class);
        $this->mapper                = $this->createMock(WaitingRoomEntryMapper::class);
        $this->entityManager         = $this->createMock(EntityManagerInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->handler = new CancelAppointmentHandler(
            $this->appointmentRepository,
            $this->entryRepository,
            $this->mapper,
            $this->entityManager,
            $this->clock,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCancelAppointmentClosesActiveWaitingRoomEntry(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);
        $entryEntity   = $this->createMock(WaitingRoomEntryEntity::class);
        $entry         = $this->makeWaitingRoomEntry($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Appointment $a): bool => AppointmentStatus::CANCELLED === $a->status()))
        ;

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn($entryEntity);
        $this->entityManager->expects(self::once())
            ->method('getRepository')
            ->with(WaitingRoomEntryEntity::class)
            ->willReturn($repository)
        ;

        $this->mapper->expects(self::once())->method('toDomain')->with($entryEntity)->willReturn($entry);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:30:00'));
        $this->entryRepository->expects(self::once())->method('save');

        ($this->handler)(new CancelAppointment($appointmentId->toString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCancelAppointmentWithoutActiveWaitingRoomEntry(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::once())->method('save');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::once())->method('getRepository')->willReturn($repository);

        $this->mapper->expects(self::never())->method('toDomain');
        $this->entryRepository->expects(self::never())->method('save');

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

    private function makeWaitingRoomEntry(AppointmentId $appointmentId): WaitingRoomEntry
    {
        return WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('33333333-3333-3333-3333-333333333333'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            linkedAppointmentId: $appointmentId,
            ownerId: null,
            animalId: null,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );
    }
}
