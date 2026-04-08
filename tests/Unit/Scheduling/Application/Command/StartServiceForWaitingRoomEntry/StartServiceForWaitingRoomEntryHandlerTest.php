<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\Command\StartServiceForWaitingRoomEntry;

use App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry;
use App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntryHandler;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StartServiceForWaitingRoomEntryHandlerTest extends TestCase
{
    private WaitingRoomEntryRepositoryInterface&MockObject $repository;
    private ClockInterface&MockObject $clock;
    private StartServiceForWaitingRoomEntryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WaitingRoomEntryRepositoryInterface::class);
        $this->clock      = $this->createMock(ClockInterface::class);
        $this->handler    = new StartServiceForWaitingRoomEntryHandler($this->repository, $this->clock);
    }

    public function testStartServiceWithUserId(): void
    {
        $entry = $this->makeEntry();
        $this->repository->expects(self::once())->method('findById')->willReturn($entry);
        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-04-10 09:05:00'))
        ;
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (WaitingRoomEntry $e): bool => WaitingRoomEntryStatus::IN_SERVICE === $e->status()))
        ;

        ($this->handler)(new StartServiceForWaitingRoomEntry(
            waitingRoomEntryId: '11111111-1111-1111-1111-111111111111',
            serviceStartedByUserId: '55555555-5555-5555-5555-555555555555',
        ));
    }

    public function testStartServiceWithoutUserId(): void
    {
        $entry = $this->makeEntry();
        $this->repository->expects(self::once())->method('findById')->willReturn($entry);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:05:00'));
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new StartServiceForWaitingRoomEntry(
            waitingRoomEntryId: '11111111-1111-1111-1111-111111111111',
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenEntryNotFound(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Waiting room entry with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new StartServiceForWaitingRoomEntry(
            waitingRoomEntryId: '11111111-1111-1111-1111-111111111111',
        ));
    }

    private function makeEntry(): WaitingRoomEntry
    {
        return WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            linkedAppointmentId: AppointmentId::fromString('33333333-3333-3333-3333-333333333333'),
            ownerId: null,
            animalId: null,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );
    }
}
