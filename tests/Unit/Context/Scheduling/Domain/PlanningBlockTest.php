<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\Event\PlanningBlockCreated;
use App\Context\Scheduling\Domain\Event\PlanningBlockDeleted;
use App\Context\Scheduling\Domain\Event\PlanningBlockUpdated;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PlanningBlockTest extends TestCase
{
    public function testCreateEmitsPlanningBlockCreated(): void
    {
        $block  = $this->createSampleBlock();
        $events = $block->recordedDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PlanningBlockCreated::class, $events[0]);
        self::assertSame('11111111-1111-1111-1111-111111111111', $events[0]->aggregateId());
    }

    public function testReconstituteEmitsNoEvents(): void
    {
        $block = PlanningBlock::reconstitute(
            id: PlanningBlockId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            staffUserId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange('2026-04-21', '08:00', '12:00'),
            capacityPerHour: 3,
            rule: RecurrenceRule::weekly(),
            note: null,
        );

        self::assertEmpty($block->recordedDomainEvents());
    }

    public function testUpdateEmitsPlanningBlockUpdated(): void
    {
        $block = $this->createSampleBlock();
        $_     = $block->pullDomainEvents();

        $newRange = new TimeRange('2026-04-21', '09:00', '13:00');
        $block->update(PlanningBlockType::SURGERY, $newRange, 2, RecurrenceRule::weekly(), 'Updated note');

        $events = $block->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PlanningBlockUpdated::class, $events[0]);

        self::assertSame(PlanningBlockType::SURGERY, $block->type());
        self::assertTrue($newRange->equals($block->timeRange()));
        self::assertSame(2, $block->capacityPerHour());
        self::assertSame('Updated note', $block->note());
    }

    public function testDeleteEmitsPlanningBlockDeleted(): void
    {
        $block = $this->createSampleBlock();
        $_     = $block->pullDomainEvents();

        $block->delete();

        $events = $block->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PlanningBlockDeleted::class, $events[0]);
    }

    public function testGetters(): void
    {
        $block = $this->createSampleBlock();

        self::assertSame('11111111-1111-1111-1111-111111111111', $block->id()->toString());
        self::assertSame('22222222-2222-2222-2222-222222222222', $block->clinicId()->toString());
        self::assertSame('33333333-3333-3333-3333-333333333333', $block->staffUserId()->toString());
        self::assertSame(PlanningBlockType::CONSULTATION, $block->type());
        self::assertSame(3, $block->capacityPerHour());
        self::assertSame('Test note', $block->note());
    }

    private function createSampleBlock(): PlanningBlock
    {
        return PlanningBlock::create(
            id: PlanningBlockId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            staffUserId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange('2026-04-21', '08:00', '12:00'),
            capacityPerHour: 3,
            rule: RecurrenceRule::weekly(),
            note: 'Test note',
        );
    }
}
