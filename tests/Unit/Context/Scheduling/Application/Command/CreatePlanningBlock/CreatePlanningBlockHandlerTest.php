<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CreatePlanningBlock;

use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlock;
use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
use App\Context\Scheduling\Domain\Exception\InvalidPlanningBlockTimeRange;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreatePlanningBlockHandlerTest extends TestCase
{
    private PlanningBlockRepositoryInterface&MockObject $repository;
    private PlanningBlockOverlapCheckerInterface&MockObject $overlapChecker;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private CreatePlanningBlockHandler $handler;

    protected function setUp(): void
    {
        $this->repository     = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->overlapChecker = $this->createMock(PlanningBlockOverlapCheckerInterface::class);
        $this->uuidGenerator  = $this->createMock(UuidGeneratorInterface::class);

        $this->handler = new CreatePlanningBlockHandler(
            repository: $this->repository,
            overlapChecker: $this->overlapChecker,
            uuidGenerator: $this->uuidGenerator,
        );
    }

    public function testHappyPath(): void
    {
        $this->overlapChecker->expects(self::once())->method('hasOverlap')->willReturn(false);
        $this->uuidGenerator->expects(self::once())->method('generate')
            ->willReturn('01234567-89ab-cdef-0123-456789abcdef')
        ;
        $this->repository->expects(self::once())->method('save')
            ->with(self::callback(fn ($b) => $b instanceof PlanningBlock))
        ;

        $id = ($this->handler)(new CreatePlanningBlock(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '22222222-2222-2222-2222-222222222222',
            type: 'consultation',
            date: '2026-04-21',
            startTime: '08:00',
            endTime: '12:00',
            capacityPerHour: 3,
            recurrenceFreq: 'WEEKLY',
            recurrenceUntil: null,
            note: null,
        ));

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $id);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testOverlapThrows(): void
    {
        $this->expectException(CannotCreateOverlappingPlanningBlock::class);

        $this->overlapChecker->method('hasOverlap')->willReturn(true);

        ($this->handler)(new CreatePlanningBlock(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '22222222-2222-2222-2222-222222222222',
            type: 'consultation',
            date: '2026-04-21',
            startTime: '08:00',
            endTime: '12:00',
            capacityPerHour: 3,
            recurrenceFreq: 'NONE',
            recurrenceUntil: null,
            note: null,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInvalidTimeRangePropagates(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);

        ($this->handler)(new CreatePlanningBlock(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '22222222-2222-2222-2222-222222222222',
            type: 'consultation',
            date: '2026-04-21',
            startTime: '12:00',
            endTime: '08:00',
            capacityPerHour: 3,
            recurrenceFreq: 'NONE',
            recurrenceUntil: null,
            note: null,
        ));
    }
}
