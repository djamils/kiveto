<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CreatePlanningBlock;

use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlock;
use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlockHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
use App\Context\Scheduling\Domain\Exception\InvalidPlanningBlockTimeRange;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreatePlanningBlockHandlerTest extends TestCase
{
    private PlanningBlockRepositoryInterface&MockObject $repository;
    private PlanningBlockOverlapCheckerInterface&MockObject $overlapChecker;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private CreatePlanningBlockHandler $handler;
    private RecordingDayLock $dayLock;

    protected function setUp(): void
    {
        $this->repository     = $this->createMock(PlanningBlockRepositoryInterface::class);
        $this->overlapChecker = $this->createMock(PlanningBlockOverlapCheckerInterface::class);
        $this->uuidGenerator  = $this->createMock(UuidGeneratorInterface::class);

        $this->handler = new CreatePlanningBlockHandler(
            repository: $this->repository,
            overlapChecker: $this->overlapChecker,
            uuidGenerator: $this->uuidGenerator,
            timezoneResolver: new class implements ClinicTimezoneResolverInterface {
                public function resolveTimezone(ClinicId $clinicId): \DateTimeZone
                {
                    return new \DateTimeZone('Europe/Paris');
                }
            },
            dayLock: $this->dayLock = new RecordingDayLock(),
            // 2026-04-20 23:30 UTC is already the 21st in Paris
            clock: new MovableClock(new \DateTimeImmutable('2026-04-20 23:30', new \DateTimeZone('UTC'))),
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
            staffUserId: '22222222-2222-2222-2222-222222222222',
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
        // An open-ended weekly block locks its Tuesdays of the next 60 days, from today in Paris
        self::assertSame('11111111-1111-1111-1111-111111111111', $this->dayLock->clinicId);
        self::assertCount(9, $this->dayLock->locked);
        self::assertSame('22222222-2222-2222-2222-222222222222|2026-04-21', $this->dayLock->locked[0]);
        self::assertSame('22222222-2222-2222-2222-222222222222|2026-06-16', $this->dayLock->locked[8]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testEveryRecurrenceFrequencyIsKept(): void
    {
        $this->overlapChecker->method('hasOverlap')->willReturn(false);
        $this->uuidGenerator->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');

        $saved = [];
        $this->repository->method('save')->willReturnCallback(static function (PlanningBlock $block) use (&$saved): void {
            $saved[] = [$block->recurrenceRule()->freq(), $block->recurrenceRule()->until()];
        });

        foreach (['daily', 'WEEKDAYS'] as $freq) {
            ($this->handler)(new CreatePlanningBlock(
                clinicId: '11111111-1111-1111-1111-111111111111',
                staffUserId: '22222222-2222-2222-2222-222222222222',
                type: 'consultation',
                date: '2026-04-21',
                startTime: '08:00',
                endTime: '12:00',
                capacityPerHour: 3,
                recurrenceFreq: $freq,
                recurrenceUntil: '2026-05-31',
                note: null,
            ));
        }

        self::assertSame([['DAILY', '2026-05-31'], ['WEEKDAYS', '2026-05-31']], $saved);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAOneOffBlockLocksItsDayBeforeTheOverlapCheck(): void
    {
        $this->overlapChecker->method('hasOverlap')->willReturnCallback(function (): bool {
            self::assertSame(['22222222-2222-2222-2222-222222222222|2026-05-04'], $this->dayLock->locked);

            return true;
        });

        try {
            ($this->handler)(new CreatePlanningBlock(
                clinicId: '11111111-1111-1111-1111-111111111111',
                staffUserId: '22222222-2222-2222-2222-222222222222',
                type: 'leave',
                date: '2026-05-04',
                startTime: '08:00',
                endTime: '12:00',
                capacityPerHour: 0,
                recurrenceFreq: 'NONE',
                recurrenceUntil: null,
                note: null,
            ));
            self::fail('The overlap should be refused.');
        } catch (CannotCreateOverlappingPlanningBlock) {
            self::assertCount(1, $this->dayLock->locked);
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testOverlapThrows(): void
    {
        $this->expectException(CannotCreateOverlappingPlanningBlock::class);

        $this->overlapChecker->method('hasOverlap')->willReturn(true);

        ($this->handler)(new CreatePlanningBlock(
            clinicId: '11111111-1111-1111-1111-111111111111',
            staffUserId: '22222222-2222-2222-2222-222222222222',
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
            staffUserId: '22222222-2222-2222-2222-222222222222',
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
