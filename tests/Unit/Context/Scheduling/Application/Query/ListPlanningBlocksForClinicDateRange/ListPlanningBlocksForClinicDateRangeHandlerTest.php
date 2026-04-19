<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange;

use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRangeHandler;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListPlanningBlocksForClinicDateRangeHandlerTest extends TestCase
{
    private Connection&MockObject $connection;
    private ListPlanningBlocksForClinicDateRangeHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->handler    = new ListPlanningBlocksForClinicDateRangeHandler(
            connection: $this->connection,
            expander: new RecurrenceExpander(),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNoBlocks(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = ($this->handler)(new ListPlanningBlocksForClinicDateRange(
            clinicId: '22222222-2222-2222-2222-222222222222',
            fromDate: '2026-04-21',
            toDate: '2026-04-27',
        ));

        self::assertSame([], $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNoneBlockInRangeReturnedOnce(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([$this->row('2026-04-21')]);

        $result = ($this->handler)(new ListPlanningBlocksForClinicDateRange(
            clinicId: '22222222-2222-2222-2222-222222222222',
            fromDate: '2026-04-21',
            toDate: '2026-04-27',
        ));

        self::assertCount(1, $result);
        self::assertInstanceOf(PlanningBlockView::class, $result[0]);
        self::assertSame('2026-04-21', $result[0]->date);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNoneBlockOutOfRangeNotReturned(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([$this->row('2026-03-01')]);

        $result = ($this->handler)(new ListPlanningBlocksForClinicDateRange(
            clinicId: '22222222-2222-2222-2222-222222222222',
            fromDate: '2026-04-21',
            toDate: '2026-04-27',
        ));

        self::assertSame([], $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testWeeklyBlockProducesFourOccurrences(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([$this->row('2026-04-21', 'consultation', 'WEEKLY')])
        ;

        $result = ($this->handler)(new ListPlanningBlocksForClinicDateRange(
            clinicId: '22222222-2222-2222-2222-222222222222',
            fromDate: '2026-04-21',
            toDate: '2026-05-12',
        ));

        self::assertCount(4, $result);
        self::assertSame('2026-04-21', $result[0]->date);
        self::assertSame('2026-04-28', $result[1]->date);
        self::assertSame('2026-05-05', $result[2]->date);
        self::assertSame('2026-05-12', $result[3]->date);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSortingByDateThenStartTimeThenPractitioner(): void
    {
        $row1              = $this->row('2026-04-22');
        $row2              = $this->row('2026-04-21');
        $row2['staff_str'] = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $this->connection->method('fetchAllAssociative')->willReturn([$row1, $row2]);

        $result = ($this->handler)(new ListPlanningBlocksForClinicDateRange(
            clinicId: '22222222-2222-2222-2222-222222222222',
            fromDate: '2026-04-21',
            toDate: '2026-04-22',
        ));

        self::assertCount(2, $result);
        self::assertSame('2026-04-21', $result[0]->date);
        self::assertSame('2026-04-22', $result[1]->date);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $date, string $type = 'consultation', string $freq = 'NONE', ?string $until = null): array
    {
        return [
            'id_str'            => '11111111-1111-1111-1111-111111111111',
            'clinic_str'        => '22222222-2222-2222-2222-222222222222',
            'staff_str'         => '33333333-3333-3333-3333-333333333333',
            'type'              => $type,
            'date'              => $date,
            'start_time'        => '08:00',
            'end_time'          => '12:00',
            'capacity_per_hour' => 3,
            'recurrence_rule'   => json_encode(['freq' => $freq, 'until' => $until]),
            'note'              => null,
        ];
    }
}
