<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Planning;

use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Presentation\Clinic\ViewModel\Planning\PlanningViewBuilder;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class PlanningViewBuilderTest extends TestCase
{
    public function testPageOpensOnTheSixWeekGridOfTheSelectedMonth(): void
    {
        $bus     = $this->bus();
        $builder = $this->builder($bus, '2026-10-14 08:42:00');

        $page = $builder->page('c1', 'u1', '2026-11-03', 'week');

        self::assertSame('2026-10-14', $page['today']);
        self::assertSame(10 * 60 + 42, $page['nowMin']);
        self::assertSame('week', $page['view']);
        self::assertSame('2026-11-03', $page['date']);
        self::assertSame('u1', $page['me']);
        self::assertSame('Moi', $page['vets'][0]['court']);
        self::assertNotNull($bus->blocks);
        self::assertSame('2026-10-26', $bus->blocks->fromDate);
        self::assertSame('2026-12-06', $bus->blocks->toDate);
    }

    public function testPageFallsBackToTodayAndTheDayView(): void
    {
        $page = $this->builder($this->bus(), '2026-10-14 08:42:00')->page('c1', 'u1', '2026-02-31', 'year');

        self::assertSame('2026-10-14', $page['date']);
        self::assertSame('day', $page['view']);
    }

    public function testRangeKeepsTheDomainTypeTheSeriesAndTheAnchorDay(): void
    {
        $range = $this->builder($this->bus(), '2026-10-14 08:42:00')->range(
            'c1',
            new \DateTimeImmutable('2026-10-12'),
            new \DateTimeImmutable('2026-10-18'),
        );

        self::assertSame('2026-10-12', $range['from']);
        self::assertSame('2026-10-18', $range['to']);
        self::assertSame([
            'id'    => 'b1',
            'date'  => '2026-10-14',
            'base'  => '2026-04-22',
            'vet'   => 'u1',
            's'     => 12 * 60 + 30,
            'e'     => 14 * 60,
            't'     => 'pause',
            'type'  => 'break',
            'cap'   => 0,
            'rec'   => 'WEEKLY',
            'until' => null,
            'note'  => null,
        ], $range['blocks'][0]);
        self::assertSame('garde', $range['blocks'][1]['t']);
        self::assertSame('consult', $range['blocks'][2]['t']);
    }

    private function builder(QueryBusInterface $bus, string $nowUtc): PlanningViewBuilder
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable($nowUtc, new \DateTimeZone('UTC')));

        return new PlanningViewBuilder($bus, new AgendaViewBuilder($bus, $clock), $clock);
    }

    private function bus(): PlanningQueryBusStub
    {
        return new PlanningQueryBusStub();
    }
}
