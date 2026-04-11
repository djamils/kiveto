<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRangeHandler;
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
}
