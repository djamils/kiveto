<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Event;

use App\Context\Scheduling\Domain\Event\PlanningBlockCreated;
use App\Context\Scheduling\Domain\Event\PlanningBlockDeleted;
use App\Context\Scheduling\Domain\Event\PlanningBlockUpdated;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the planning block events expose the right aggregateId,
 * payload shape and event name.
 */
final class PlanningBlockEventsTest extends TestCase
{
    private const string BLOCK_ID  = '11111111-1111-1111-1111-111111111111';
    private const string CLINIC_ID = '22222222-2222-2222-2222-222222222222';
    private const string STAFF_ID  = '33333333-3333-3333-3333-333333333333';

    public function testPlanningBlockCreated(): void
    {
        $event = new PlanningBlockCreated(
            self::BLOCK_ID,
            self::CLINIC_ID,
            self::STAFF_ID,
            'consultation',
            '2026-04-21',
            '08:00',
            '12:00',
            3,
            'WEEKLY',
            '2026-06-30',
            'Morning slot',
        );

        self::assertSame(self::BLOCK_ID, $event->aggregateId());
        self::assertSame('scheduling.planning-block.created.v1', $event->name());
        self::assertSame($this->expectedPayload(), $event->payload());
    }

    public function testPlanningBlockUpdated(): void
    {
        $event = new PlanningBlockUpdated(
            self::BLOCK_ID,
            self::CLINIC_ID,
            self::STAFF_ID,
            'consultation',
            '2026-04-21',
            '08:00',
            '12:00',
            3,
            'WEEKLY',
            '2026-06-30',
            'Morning slot',
        );

        self::assertSame(self::BLOCK_ID, $event->aggregateId());
        self::assertSame('scheduling.planning-block.updated.v1', $event->name());
        self::assertSame($this->expectedPayload(), $event->payload());
    }

    public function testPlanningBlockDeleted(): void
    {
        $event = new PlanningBlockDeleted(self::BLOCK_ID, self::CLINIC_ID);

        self::assertSame(self::BLOCK_ID, $event->aggregateId());
        self::assertSame('scheduling.planning-block.deleted.v1', $event->name());
        self::assertSame([
            'planningBlockId' => self::BLOCK_ID,
            'clinicId'        => self::CLINIC_ID,
        ], $event->payload());
    }

    /**
     * @return array<string, int|string>
     */
    private function expectedPayload(): array
    {
        return [
            'planningBlockId' => self::BLOCK_ID,
            'clinicId'        => self::CLINIC_ID,
            'staffUserId'     => self::STAFF_ID,
            'type'            => 'consultation',
            'date'            => '2026-04-21',
            'startTime'       => '08:00',
            'endTime'         => '12:00',
            'capacityPerHour' => 3,
            'recurrenceFreq'  => 'WEEKLY',
            'recurrenceUntil' => '2026-06-30',
            'note'            => 'Morning slot',
        ];
    }
}
