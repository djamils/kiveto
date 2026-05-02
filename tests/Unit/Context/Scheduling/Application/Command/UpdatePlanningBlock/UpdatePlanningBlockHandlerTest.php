<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\UpdatePlanningBlock;

use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlock;
use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Exception\CannotChangePlanningBlockTypeWithActiveAppointments;
use App\Context\Scheduling\Domain\Exception\CannotModifyRecurrenceRuleOnExistingBlock;
use App\Context\Scheduling\Domain\Exception\CannotReduceCapacityBelowExistingAppointmentCount;
use App\Context\Scheduling\Domain\Exception\CannotShrinkPlanningBlockBelowExistingAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdatePlanningBlockHandlerTest extends TestCase
{
    private PlanningBlockRepositoryInterface&MockObject $repository;
    private PlanningBlockOverlapCheckerInterface&MockObject $overlapChecker;
    private PlanningBlockAppointmentCounterInterface&MockObject $counter;
    private ClinicTimezoneResolverInterface&MockObject $timezoneResolver;
    private UpdatePlanningBlockHandler $handler;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->overlapChecker   = $this->createMock(PlanningBlockOverlapCheckerInterface::class);
        $this->counter          = $this->createMock(PlanningBlockAppointmentCounterInterface::class);
        $this->timezoneResolver = $this->createMock(ClinicTimezoneResolverInterface::class);

        $this->handler = new UpdatePlanningBlockHandler(
            repository: $this->repository,
            overlapChecker: $this->overlapChecker,
            appointmentCounter: $this->counter,
            timezoneResolver: $this->timezoneResolver,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHappyPath(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');

        ($this->handler)($this->baseCommand());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockNotFoundThrows(): void
    {
        $this->expectException(PlanningBlockNotFoundException::class);
        $this->repository->method('findById')->willReturn(null);

        ($this->handler)($this->baseCommand());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD1TypeChangeWithActiveAppointmentsThrows(): void
    {
        $this->expectException(CannotChangePlanningBlockTypeWithActiveAppointments::class);
        $this->setupHappyPath(appointmentCount: 1);

        ($this->handler)($this->baseCommand(['type' => 'surgery']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD2ShrinkWindowWithActiveAppointmentsThrows(): void
    {
        $this->expectException(CannotShrinkPlanningBlockBelowExistingAppointments::class);
        $this->setupHappyPath(appointmentCount: 1);

        ($this->handler)($this->baseCommand(['endTime' => '10:00']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD3ReduceCapacityBelowCountThrows(): void
    {
        $this->expectException(CannotReduceCapacityBelowExistingAppointmentCount::class);
        $this->setupHappyPath(appointmentCount: 5);

        // 1 cap/h * 4h = 4 capacity, but 5 appointments exist
        ($this->handler)($this->baseCommand(['capacityPerHour' => 1]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD4ModifyRecurrenceFreqThrows(): void
    {
        $this->expectException(CannotModifyRecurrenceRuleOnExistingBlock::class);
        $this->setupHappyPath();

        ($this->handler)($this->baseCommand(['recurrenceFreq' => 'DAILY']));
    }

    private function sampleBlock(): PlanningBlock
    {
        return PlanningBlock::reconstitute(
            id: PlanningBlockId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            staffUserId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange('2026-04-21', '08:00', '12:00'),
            capacityPerHour: 3,
            rule: RecurrenceRule::weekly(),
            note: null,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function baseCommand(array $overrides = []): UpdatePlanningBlock
    {
        return new UpdatePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            staffUserId: '33333333-3333-3333-3333-333333333333',
            type: \is_string($overrides['type'] ?? null) ? $overrides['type'] : 'consultation',
            date: \is_string($overrides['date'] ?? null) ? $overrides['date'] : '2026-04-21',
            startTime: \is_string($overrides['startTime'] ?? null) ? $overrides['startTime'] : '08:00',
            endTime: \is_string($overrides['endTime'] ?? null) ? $overrides['endTime'] : '12:00',
            capacityPerHour: \is_int($overrides['capacityPerHour'] ?? null) ? $overrides['capacityPerHour'] : 3,
            recurrenceFreq: \is_string($overrides['recurrenceFreq'] ?? null) ? $overrides['recurrenceFreq'] : 'WEEKLY',
            recurrenceUntil: \is_string($overrides['recurrenceUntil'] ?? null) ? $overrides['recurrenceUntil'] : null,
            note: null,
        );
    }

    private function setupHappyPath(int $appointmentCount = 0): void
    {
        $this->repository->method('findById')->willReturn($this->sampleBlock());
        $this->overlapChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->counter->method('countActiveInWindow')->willReturn($appointmentCount);
    }
}
