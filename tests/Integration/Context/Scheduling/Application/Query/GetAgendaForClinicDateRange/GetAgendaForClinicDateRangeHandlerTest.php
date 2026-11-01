<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRangeHandler;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetAgendaForClinicDateRangeHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsAppointmentsForClinicAndDay(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'))
            ->create()
        ;
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 14:30:00'))
            ->create()
        ;
        // Different day — should not appear
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-11 09:00:00'))
            ->create()
        ;
        // Different clinic — should not appear
        AppointmentEntityFactory::new()
            ->withClinicId('99999999-9999-9999-9999-999999999999')
            ->startingAt(new \DateTimeImmutable('2026-04-10 10:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $items = $handler(GetAgendaForClinicDateRange::forDay(
            clinicId: $clinicId,
            date: new \DateTimeImmutable('2026-04-10'),
            clinicTz: new \DateTimeZone('UTC'),
        ));

        self::assertCount(2, $items);
        foreach ($items as $item) {
            self::assertInstanceOf(AppointmentItem::class, $item);
            self::assertSame($clinicId, $item->clinicId);
        }
    }

    public function testFiltersByPractitioner(): void
    {
        $clinicId           = '12345678-9abc-def0-1234-56789abcdef0';
        $practitionerUserId = '44444444-4444-4444-4444-444444444444';

        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId($practitionerUserId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'))
            ->create()
        ;
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId('55555555-5555-5555-5555-555555555555')
            ->startingAt(new \DateTimeImmutable('2026-04-10 10:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $items = $handler(GetAgendaForClinicDateRange::forDay(
            clinicId: $clinicId,
            date: new \DateTimeImmutable('2026-04-10'),
            clinicTz: new \DateTimeZone('UTC'),
            practitionerUserId: $practitionerUserId,
        ));

        self::assertCount(1, $items);
        self::assertSame($practitionerUserId, $items[0]->practitionerUserId);
    }

    public function testReturnsEmptyArrayWhenNoMatches(): void
    {
        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $items = $handler(GetAgendaForClinicDateRange::forDay(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            date: new \DateTimeImmutable('2026-04-10'),
            clinicTz: new \DateTimeZone('UTC'),
        ));

        self::assertSame([], $items);
    }

    public function testForWeekReturnsAppointmentsAcrossSevenDayRange(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        // Monday, Wednesday, Sunday of the target Paris week
        foreach (['2026-04-13 09:00:00', '2026-04-15 14:00:00', '2026-04-19 10:00:00'] as $startsAt) {
            AppointmentEntityFactory::new()
                ->withClinicId($clinicId)
                ->startingAt(new \DateTimeImmutable($startsAt))
                ->create()
            ;
        }

        // Outside the week — previous Sunday & next Monday
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-12 09:00:00'))
            ->create()
        ;
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-20 09:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $items = $handler(GetAgendaForClinicDateRange::forWeek(
            clinicId: $clinicId,
            anchorDate: new \DateTimeImmutable('2026-04-15 12:00:00'),
            clinicTz: new \DateTimeZone('UTC'),
        ));

        self::assertCount(3, $items);
    }

    public function testForWeekUsesClinicTimezoneWhenComputingWeekBoundaries(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        // Anchor: UTC Sunday 2026-04-12 23:30 = Paris Monday 2026-04-13 01:30 (CEST, +02:00).
        // In Paris that is a Monday → "monday this week" in Paris is 2026-04-13.
        // In UTC (wrong interpretation) that is a Sunday → "monday this week" is
        // 2026-04-06, a completely different week. This test proves the clinic
        // timezone is applied BEFORE computing the week boundaries.

        // Should be included (Paris week of 2026-04-13 → 2026-04-19):
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-13 08:00:00'))
            ->create()
        ;

        // Should be EXCLUDED (previous Paris week, included only if the wrong
        // UTC-based week computation were used):
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-08 08:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $query = GetAgendaForClinicDateRange::forWeek(
            clinicId: $clinicId,
            anchorDate: new \DateTimeImmutable('2026-04-12 23:30:00', new \DateTimeZone('UTC')),
            clinicTz: new \DateTimeZone('Europe/Paris'),
        );

        $items = $handler($query);

        self::assertCount(1, $items);
        self::assertSame('2026-04-13 08:00:00', $items[0]->startsAtUtc);
    }

    public function testAnAppointmentInsideAnOpenBlockOfItsPractitionerIsNotOrphaned(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcd0a1';
        $vetId    = '01234567-89ab-cdef-0123-456789abc0a1';
        $otherVet = '01234567-89ab-cdef-0123-456789abc0a2';

        ClinicEntityFactory::new()->withId($clinicId)->create(['timeZone' => 'Europe/Paris']);

        // Weekly consultation block on Mondays 08:00–12:00 Paris (06:00–10:00 UTC in April)
        $this->block('01960000-0000-7000-8000-0000000b0a01', $clinicId, $vetId, PlanningBlockType::CONSULTATION, '2026-04-06', '08:00', '12:00', RecurrenceRule::weekly());
        // Leave of the same vet in the afternoon: it never accepts bookings
        $this->block('01960000-0000-7000-8000-0000000b0a02', $clinicId, $vetId, PlanningBlockType::LEAVE, '2026-04-13', '14:00', '18:00', RecurrenceRule::none());

        // Inside the consultation block → planned
        AppointmentEntityFactory::new()->withClinicId($clinicId)->withPractitionerUserId($vetId)
            ->startingAt(new \DateTimeImmutable('2026-04-13 07:00:00'), 30)->create()
        ;
        // Inside the leave → orphaned
        AppointmentEntityFactory::new()->withClinicId($clinicId)->withPractitionerUserId($vetId)
            ->startingAt(new \DateTimeImmutable('2026-04-13 13:00:00'), 30)->create()
        ;
        // Another vet with no block → orphaned
        AppointmentEntityFactory::new()->withClinicId($clinicId)->withPractitionerUserId($otherVet)
            ->startingAt(new \DateTimeImmutable('2026-04-13 07:30:00'), 30)->create()
        ;
        // Overflows the end of the block → orphaned
        AppointmentEntityFactory::new()->withClinicId($clinicId)->withPractitionerUserId($vetId)
            ->startingAt(new \DateTimeImmutable('2026-04-13 09:45:00'), 30)->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDateRangeHandler::class);
        \assert($handler instanceof GetAgendaForClinicDateRangeHandler);

        $items = $handler(GetAgendaForClinicDateRange::forDay(
            clinicId: $clinicId,
            date: new \DateTimeImmutable('2026-04-13'),
            clinicTz: new \DateTimeZone('Europe/Paris'),
        ));

        $orphaned = [];
        foreach ($items as $item) {
            $orphaned[$item->startsAtUtc] = $item->isOrphaned;
        }

        self::assertSame([
            '2026-04-13 07:00:00' => false,
            '2026-04-13 07:30:00' => true,
            '2026-04-13 09:45:00' => true,
            '2026-04-13 13:00:00' => true,
        ], $orphaned);
    }

    private function block(string $id, string $clinicId, string $vetId, PlanningBlockType $type, string $date, string $start, string $end, RecurrenceRule $rule): void
    {
        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);

        $blocks->save(PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString($clinicId),
            staffUserId: UserId::fromString($vetId),
            type: $type,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: PlanningBlockType::LEAVE === $type ? 0 : 4,
            rule: $rule,
            note: null,
        ));
    }
}
