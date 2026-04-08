<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry;

use App\Context\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry\CreateWaitingRoomWalkInEntry;
use App\Context\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry\CreateWaitingRoomWalkInEntryHandler;
use App\Context\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Context\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Context\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Context\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateWaitingRoomWalkInEntryHandlerTest extends TestCase
{
    private const string CLINIC_ID = '11111111-1111-1111-1111-111111111111';
    private const string OWNER_ID  = '22222222-2222-2222-2222-222222222222';
    private const string ANIMAL_ID = '33333333-3333-3333-3333-333333333333';
    private const string ENTRY_ID  = '44444444-4444-4444-4444-444444444444';

    private WaitingRoomEntryRepositoryInterface&MockObject $repository;
    private OwnerExistenceCheckerInterface&MockObject $ownerChecker;
    private AnimalExistenceCheckerInterface&MockObject $animalChecker;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private CreateWaitingRoomWalkInEntryHandler $handler;

    protected function setUp(): void
    {
        $this->repository    = $this->createMock(WaitingRoomEntryRepositoryInterface::class);
        $this->ownerChecker  = $this->createMock(OwnerExistenceCheckerInterface::class);
        $this->animalChecker = $this->createMock(AnimalExistenceCheckerInterface::class);
        $this->uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);

        $this->handler = new CreateWaitingRoomWalkInEntryHandler(
            $this->repository,
            $this->ownerChecker,
            $this->animalChecker,
            $this->uuidGenerator,
            $this->clock,
        );
    }

    public function testCreateWalkInWithKnownOwnerAndAnimal(): void
    {
        $this->ownerChecker->expects(self::once())->method('exists')->willReturn(true);
        $this->animalChecker->expects(self::once())->method('exists')->willReturn(true);
        $this->uuidGenerator->expects(self::once())->method('generate')->willReturn(self::ENTRY_ID);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 11:00:00'));
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(WaitingRoomEntry::class))
        ;

        $entryId = ($this->handler)(new CreateWaitingRoomWalkInEntry(
            clinicId: self::CLINIC_ID,
            ownerId: self::OWNER_ID,
            animalId: self::ANIMAL_ID,
        ));

        self::assertSame(self::ENTRY_ID, $entryId);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCreateWalkInForUnknownAnimal(): void
    {
        $this->uuidGenerator->expects(self::once())->method('generate')->willReturn(self::ENTRY_ID);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 11:00:00'));
        $this->repository->expects(self::once())->method('save');

        $entryId = ($this->handler)(new CreateWaitingRoomWalkInEntry(
            clinicId: self::CLINIC_ID,
            foundAnimalDescription: 'Tabby cat, no collar',
            arrivalMode: 'EMERGENCY',
            priority: 5,
            triageNotes: 'Bleeding',
        ));

        self::assertSame(self::ENTRY_ID, $entryId);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenOwnerDoesNotExist(): void
    {
        $this->ownerChecker->expects(self::once())->method('exists')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner with ID "' . self::OWNER_ID . '" does not exist.');

        ($this->handler)(new CreateWaitingRoomWalkInEntry(
            clinicId: self::CLINIC_ID,
            ownerId: self::OWNER_ID,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAnimalDoesNotExist(): void
    {
        $this->animalChecker->expects(self::once())->method('exists')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Animal with ID "' . self::ANIMAL_ID . '" does not exist.');

        ($this->handler)(new CreateWaitingRoomWalkInEntry(
            clinicId: self::CLINIC_ID,
            animalId: self::ANIMAL_ID,
        ));
    }
}
