<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\UpdatePlanningBlock;

use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlock;
use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Exception\CannotChangePlanningBlockTypeWithActiveAppointments;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
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
use App\Tests\Unit\Context\Scheduling\Support\FakeOccupyingAppointments;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The block: weekly on Tuesdays from 21 April 2026, 08:00–12:00 Paris
 * (06:00–10:00 UTC), 3 per hour; today is 1 April 2026.
 */
final class UpdatePlanningBlockHandlerTest extends TestCase
{
    private const string STAFF = '33333333-3333-3333-3333-333333333333';
    private const string OTHER = '44444444-4444-4444-4444-444444444444';

    private PlanningBlockRepositoryInterface&MockObject $repository;
    private PlanningBlockOverlapCheckerInterface&MockObject $overlapChecker;
    private FakeOccupyingAppointments $appointments;
    private ClinicTimezoneResolverInterface&MockObject $timezoneResolver;
    private UpdatePlanningBlockHandler $handler;
    private RecordingDayLock $dayLock;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->overlapChecker   = $this->createMock(PlanningBlockOverlapCheckerInterface::class);
        $this->appointments     = new FakeOccupyingAppointments();
        $this->timezoneResolver = $this->createMock(ClinicTimezoneResolverInterface::class);

        $this->handler = new UpdatePlanningBlockHandler(
            repository: $this->repository,
            overlapChecker: $this->overlapChecker,
            appointmentsReader: $this->appointments,
            timezoneResolver: $this->timezoneResolver,
            dayLock: $this->dayLock = new RecordingDayLock(),
            clock: new MovableClock(new \DateTimeImmutable('2026-04-01 08:00', new \DateTimeZone('UTC'))),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHappyPath(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');

        ($this->handler)($this->baseCommand());

        // The weekly block's Tuesdays of the next 60 days
        self::assertCount(6, $this->dayLock->locked);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAStaffChangeLocksTheDaysOfBothStaffMembers(): void
    {
        $this->setupHappyPath();

        ($this->handler)(new UpdatePlanningBlock(
            blockId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            staffUserId: '44444444-4444-4444-4444-444444444444',
            type: 'consultation',
            date: '2026-04-21',
            startTime: '08:00',
            endTime: '12:00',
            capacityPerHour: 3,
            recurrenceFreq: 'WEEKLY',
            recurrenceUntil: null,
            note: null,
        ));

        self::assertCount(12, $this->dayLock->locked);
        self::assertSame('33333333-3333-3333-3333-333333333333|2026-04-21', $this->dayLock->locked[0]);
        self::assertSame('44444444-4444-4444-4444-444444444444|2026-05-26', $this->dayLock->locked[11]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockNotFoundThrows(): void
    {
        $this->expectException(PlanningBlockNotFoundException::class);
        $this->repository->method('findById')->willReturn(null);

        ($this->handler)($this->baseCommand());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnUpdateOverlappingAnotherBlockIsRefusedLikeACreation(): void
    {
        $this->setupHappyPath(overlap: true);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(CannotCreateOverlappingPlanningBlock::class);

        ($this->handler)($this->baseCommand(['startTime' => '07:00']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD1TypeChangeWithAnAppointmentInALaterOccurrenceThrows(): void
    {
        $this->expectException(CannotChangePlanningBlockTypeWithActiveAppointments::class);
        $this->setupHappyPath();
        // The sixth Tuesday, far from the first occurrence
        $this->appointments->add(self::STAFF, '2026-05-26 07:00');

        ($this->handler)($this->baseCommand(['type' => 'surgery']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD2ShrinkPastAnAppointmentOfAnyOccurrenceThrows(): void
    {
        $this->expectException(CannotShrinkPlanningBlockBelowExistingAppointments::class);
        $this->setupHappyPath();
        // 11:00 Paris on the third Tuesday: outside 08:00–10:00
        $this->appointments->add(self::STAFF, '2026-05-05 09:00');

        ($this->handler)($this->baseCommand(['endTime' => '10:00']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD2ShrinkingWithEveryAppointmentStillInsideIsAllowed(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');
        // 08:30 Paris: still inside 08:00–10:00
        $this->appointments->add(self::STAFF, '2026-05-05 06:30');

        ($this->handler)($this->baseCommand(['endTime' => '10:00']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnAppointmentStraddlingTheEdgeDoesNotBlockAnEditThatKeepsTheWindow(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');
        // 11:45–12:15 Paris: overlaps the block's end
        $this->appointments->add(self::STAFF, '2026-05-05 09:45');

        ($this->handler)($this->baseCommand(['capacityPerHour' => 2]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAStraddlingAppointmentLeftBehindByAMoveThrows(): void
    {
        $this->expectException(CannotShrinkPlanningBlockBelowExistingAppointments::class);
        $this->setupHappyPath();
        $this->appointments->add(self::STAFF, '2026-05-05 09:45');

        ($this->handler)($this->baseCommand(['endTime' => '11:00']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD5AStaffChangeCountsTheAppointmentsOfTheOriginalPractitioner(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::never())->method('save');
        $this->appointments->add(self::STAFF, '2026-05-12 07:00');

        try {
            ($this->handler)($this->baseCommand(['staffUserId' => self::OTHER]));
            self::fail('The original practitioner\'s appointment would lose its block.');
        } catch (CannotShrinkPlanningBlockBelowExistingAppointments) {
            self::assertSame(self::STAFF . '|2026-04-21 06:00|9999-12-31 00:00|current', $this->appointments->reads[0]);
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD5TheNewPractitionersOwnAppointmentsDoNotBlockAStaffChange(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');
        $this->appointments->add(self::OTHER, '2026-05-12 07:00');

        ($this->handler)($this->baseCommand(['staffUserId' => self::OTHER]));

        // The capacity is checked on the new practitioner's appointments
        self::assertSame(self::OTHER . '|2026-04-21 06:00|9999-12-31 00:00|current', $this->appointments->reads[1]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD3ReduceCapacityBelowTheAppointmentsOfOneOccurrenceThrows(): void
    {
        $this->expectException(CannotReduceCapacityBelowExistingAppointmentCount::class);
        $this->setupHappyPath();
        // 1 cap/h * 4h = 4 capacity, but 5 appointments on 12 May
        foreach (['06:00', '06:30', '07:00', '07:30', '08:00'] as $time) {
            $this->appointments->add(self::STAFF, '2026-05-12 ' . $time);
        }

        ($this->handler)($this->baseCommand(['capacityPerHour' => 1]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD3CapacityIsPerOccurrence(): void
    {
        $this->setupHappyPath();
        $this->repository->expects(self::once())->method('save');
        // 5 appointments, one per Tuesday: 1 per occurrence fits 4
        foreach (['04-21', '04-28', '05-05', '05-12', '05-19'] as $day) {
            $this->appointments->add(self::STAFF, '2026-' . $day . ' 07:00');
        }

        ($this->handler)($this->baseCommand(['capacityPerHour' => 1]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testD4ModifyRecurrenceFreqThrows(): void
    {
        $this->expectException(CannotModifyRecurrenceRuleOnExistingBlock::class);
        $this->setupHappyPath();

        ($this->handler)($this->baseCommand(['recurrenceFreq' => 'DAILY']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSwitchingAWeeklyBlockToWeekdaysThrows(): void
    {
        $this->expectException(CannotModifyRecurrenceRuleOnExistingBlock::class);
        $this->setupHappyPath();

        ($this->handler)($this->baseCommand(['recurrenceFreq' => 'weekdays']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockOfAnotherClinicIsReportedAsNotFound(): void
    {
        $this->repository->expects(self::once())->method('findById')->willReturn($this->sampleBlock());
        $this->overlapChecker->expects(self::never())->method('hasOverlap');
        $this->repository->expects(self::never())->method('save');

        $this->expectException(PlanningBlockNotFoundException::class);

        ($this->handler)($this->baseCommand(['clinicId' => '99999999-9999-9999-9999-999999999999']));
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
            clinicId: \is_string($overrides['clinicId'] ?? null) ? $overrides['clinicId'] : '22222222-2222-2222-2222-222222222222',
            staffUserId: \is_string($overrides['staffUserId'] ?? null) ? $overrides['staffUserId'] : self::STAFF,
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

    private function setupHappyPath(bool $overlap = false): void
    {
        $this->repository->method('findById')->willReturn($this->sampleBlock());
        $this->overlapChecker->method('hasOverlap')->willReturn($overlap);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
    }
}
