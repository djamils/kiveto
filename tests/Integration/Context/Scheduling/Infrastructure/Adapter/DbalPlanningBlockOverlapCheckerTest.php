<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * FU-06, through SQL: today is Wednesday 1 April 2026; Dr Rousseau holds a
 * weekly block on Tuesdays 08:00–12:00 since 3 March.
 */
final class DbalPlanningBlockOverlapCheckerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC   = '12345678-9abc-def0-1234-56789abc0f01';
    private const string ROUSSEAU = '01234567-89ab-cdef-0123-456789ab0f01';
    private const string LAMBERT  = '01234567-89ab-cdef-0123-456789ab0f02';
    private const string WEEKLY   = '01960000-0000-7000-8000-0000000b0f01';
    private const string TODAY    = '2026-04-01';

    private PlanningBlockOverlapCheckerInterface $checker;

    protected function setUp(): void
    {
        $checker = self::getContainer()->get(PlanningBlockOverlapCheckerInterface::class);
        \assert($checker instanceof PlanningBlockOverlapCheckerInterface);
        $this->checker = $checker;

        ClinicEntityFactory::new()->withId(self::CLINIC)->create(['timeZone' => 'Europe/Paris']);
        $this->block(self::WEEKLY, self::ROUSSEAU, '2026-03-03', '08:00', '12:00', RecurrenceRule::weekly());
    }

    public function testAnOccurrenceOfAWeeklyBlockThatBeganEarlierIsMet(): void
    {
        self::assertTrue($this->overlaps(self::ROUSSEAU, '2026-04-21', '11:00', '13:00', RecurrenceRule::none()));
        self::assertTrue($this->overlaps(self::ROUSSEAU, '2026-04-14', '09:00', '10:00', RecurrenceRule::weekly()));
    }

    public function testNoOverlapOffTheOccurrencesOrForAnotherStaffMember(): void
    {
        // A Wednesday; touching the end; Dr Lambert
        self::assertFalse($this->overlaps(self::ROUSSEAU, '2026-04-22', '09:00', '10:00', RecurrenceRule::none()));
        self::assertFalse($this->overlaps(self::ROUSSEAU, '2026-04-21', '12:00', '13:00', RecurrenceRule::weekly()));
        self::assertFalse($this->overlaps(self::LAMBERT, '2026-04-21', '09:00', '10:00', RecurrenceRule::none()));
    }

    public function testAWeeklyBlockOverOrTheBlockItselfIsNotMet(): void
    {
        self::assertFalse($this->overlaps(self::ROUSSEAU, '2026-04-21', '09:00', '10:00', RecurrenceRule::none(), exclude: self::WEEKLY));

        // Ended before the date compared
        $this->block('01960000-0000-7000-8000-0000000b0f02', self::LAMBERT, '2026-03-03', '08:00', '12:00', RecurrenceRule::weekly('2026-04-10'));
        self::assertFalse($this->overlaps(self::LAMBERT, '2026-04-21', '09:00', '10:00', RecurrenceRule::none()));
        self::assertTrue($this->overlaps(self::LAMBERT, '2026-04-07', '09:00', '10:00', RecurrenceRule::none()));

        // A recurring candidate already over occurs nowhere
        self::assertFalse($this->overlaps(self::ROUSSEAU, '2026-03-10', '09:00', '10:00', RecurrenceRule::weekly('2026-03-31')));
    }

    private function overlaps(string $staff, string $date, string $start, string $end, RecurrenceRule $rule, ?string $exclude = null): bool
    {
        return $this->checker->hasOverlap(
            ClinicId::fromString(self::CLINIC),
            UserId::fromString($staff),
            new TimeRange($date, $start, $end),
            $rule,
            self::TODAY,
            null !== $exclude ? PlanningBlockId::fromString($exclude) : null,
        );
    }

    private function block(string $id, string $staff, string $date, string $start, string $end, RecurrenceRule $rule): void
    {
        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);

        $blocks->save(PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            staffUserId: UserId::fromString($staff),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: 4,
            rule: $rule,
            note: null,
        ));
    }
}
