<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\UpdateWaitingRoomTriage;

use App\Context\Scheduling\Application\Command\UpdateWaitingRoomTriage\UpdateWaitingRoomTriage;
use App\Context\Scheduling\Application\Command\UpdateWaitingRoomTriage\UpdateWaitingRoomTriageHandler;
use App\Context\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\Scheduling\Domain\WaitingRoomEntry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateWaitingRoomTriageHandlerTest extends TestCase
{
    private WaitingRoomEntryRepositoryInterface&MockObject $repository;
    private UpdateWaitingRoomTriageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WaitingRoomEntryRepositoryInterface::class);
        $this->handler    = new UpdateWaitingRoomTriageHandler($this->repository);
    }

    public function testUpdateTriageSuccessfully(): void
    {
        $entry = $this->makeEntry();
        $this->repository->expects(self::once())->method('findById')->willReturn($entry);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new UpdateWaitingRoomTriage(
            waitingRoomEntryId: '11111111-1111-1111-1111-111111111111',
            priority: 5,
            triageNotes: 'Severe pain',
            arrivalMode: 'EMERGENCY',
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenEntryNotFound(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Waiting room entry with ID "11111111-1111-1111-1111-111111111111" does not exist.',
        );

        ($this->handler)(new UpdateWaitingRoomTriage(
            waitingRoomEntryId: '11111111-1111-1111-1111-111111111111',
            priority: 0,
            triageNotes: null,
            arrivalMode: 'STANDARD',
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
