<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Command;

use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlock;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlock;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
use App\Context\Scheduling\Domain\Exception\CannotDeletePlanningBlockWithAppointments;
use App\Context\Scheduling\Domain\Exception\CannotShrinkPlanningBlockBelowExistingAppointments;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * A weekly, open-ended block of Dr Rousseau from tomorrow, 08:00–12:00
 * Paris, through the real bus and SQL: online booking computes its slots
 * from blocks, so no mutation may leave an appointment of any future
 * occurrence without its block, and an update overlaps no other block.
 */
final class PlanningBlockMutationsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC   = '01960000-0000-7000-8000-00000000c1a1';
    private const string ROUSSEAU = '01960000-0000-7000-8000-00000000c1b1';
    private const string LAMBERT  = '01960000-0000-7000-8000-00000000c1b2';
    private const string BLOCK    = '01960000-0000-7000-8000-00000000c1d1';
    private const string OTHER    = '01960000-0000-7000-8000-00000000c1d2';

    private string $anchor;

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->create(['timeZone' => 'Europe/Paris']);

        $this->anchor = new \DateTimeImmutable('tomorrow', new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        $this->blocks()->save($this->block(self::BLOCK, $this->anchor, '08:00', '12:00', RecurrenceRule::weekly()));
    }

    public function testAnAppointmentFarBeyondTheFirstOccurrenceKeepsTheBlock(): void
    {
        // The 21st occurrence: beyond the 60 days the lock covers
        $this->appointment(self::ROUSSEAU, 140, '09:00');

        try {
            $this->bus()->dispatch(new DeletePlanningBlock(self::BLOCK, self::CLINIC));
            self::fail('The block of a future appointment was deleted.');
        } catch (CannotDeletePlanningBlockWithAppointments) {
        }

        self::assertNotNull($this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK)));
    }

    public function testAnAppointmentOffTheOccurrencesLetsTheBlockGo(): void
    {
        // The day after an occurrence, and a cancelled one in an occurrence
        $this->appointment(self::ROUSSEAU, 15, '09:00');
        $this->appointment(self::ROUSSEAU, 14, '09:00', AppointmentStatus::CANCELLED);

        $this->bus()->dispatch(new DeletePlanningBlock(self::BLOCK, self::CLINIC));

        self::assertNull($this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK)));
    }

    public function testAStaffChangeCountsTheAppointmentsOfTheOriginalPractitioner(): void
    {
        $this->appointment(self::ROUSSEAU, 70, '10:00');

        try {
            $this->bus()->dispatch($this->update(self::LAMBERT));
            self::fail('Dr Rousseau\'s appointment would have lost its block.');
        } catch (CannotShrinkPlanningBlockBelowExistingAppointments) {
        }

        self::assertSame(self::ROUSSEAU, $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK))?->staffUserId()->toString());
    }

    public function testTheNewPractitionersAppointmentsDoNotBlockTheStaffChange(): void
    {
        $this->appointment(self::LAMBERT, 70, '10:00');

        $this->bus()->dispatch($this->update(self::LAMBERT));

        self::assertSame(self::LAMBERT, $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK))?->staffUserId()->toString());
    }

    public function testAnUpdateOverlappingAnotherBlockIsRefused(): void
    {
        $this->blocks()->save($this->block(self::OTHER, $this->anchor, '13:00', '17:00', RecurrenceRule::none()));

        try {
            $this->bus()->dispatch($this->update(self::ROUSSEAU, endTime: '14:00'));
            self::fail('The block now overlaps the afternoon one.');
        } catch (CannotCreateOverlappingPlanningBlock) {
        }

        self::assertSame('12:00', $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK))?->timeRange()->endTime());
    }

    /**
     * FU-06: a one-off block on a later Tuesday meets an occurrence of the
     * weekly block, not its first date.
     */
    public function testAOneOffBlockOnALaterOccurrenceOfAWeeklyBlockIsRefused(): void
    {
        $this->expectException(CannotCreateOverlappingPlanningBlock::class);

        $this->bus()->dispatch($this->create(self::ROUSSEAU, $this->day(14), '10:00', '11:00', 'NONE'));
    }

    /**
     * FU-06: two weekly blocks on the same weekday from different first dates
     * meet on every later week; on another weekday they never do.
     */
    public function testAWeeklyBlockFromAnotherFirstDateIsRefusedOnTheSameWeekdayOnly(): void
    {
        $this->bus()->dispatch($this->create(self::ROUSSEAU, $this->day(1), '09:00', '10:00', 'WEEKLY'));

        $this->expectException(CannotCreateOverlappingPlanningBlock::class);

        $this->bus()->dispatch($this->create(self::ROUSSEAU, $this->day(7), '09:00', '10:00', 'WEEKLY'));
    }

    /**
     * FU-06: Dr Lambert's weekly block starts a week later; moving Dr
     * Rousseau's block to him overlaps it from then on.
     */
    public function testAStaffChangeOntoAnOverlappingRecurringBlockIsRefused(): void
    {
        $this->blocks()->save($this->block(self::OTHER, $this->day(7), '10:00', '11:00', RecurrenceRule::weekly(), self::LAMBERT));

        try {
            $this->bus()->dispatch($this->update(self::LAMBERT));
            self::fail('The block would overlap Dr Lambert\'s weekly block.');
        } catch (CannotCreateOverlappingPlanningBlock) {
        }

        self::assertSame(self::ROUSSEAU, $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK))?->staffUserId()->toString());
    }

    /**
     * FU-06: two blocks saved overlapping before the check went occurrence by
     * occurrence: an edit that does not move the block is still possible;
     * one that moves it must end the overlap.
     */
    public function testALegacyOverlapStaysEditableUntilTheBlockMoves(): void
    {
        $this->blocks()->save($this->block(self::OTHER, $this->day(7), '11:00', '13:00', RecurrenceRule::weekly()));

        $this->bus()->dispatch($this->update(self::ROUSSEAU, capacityPerHour: 2));
        $block = $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK));
        self::assertSame(2, $block?->capacityPerHour());

        try {
            $this->bus()->dispatch($this->update(self::ROUSSEAU, endTime: '11:30'));
            self::fail('The moved block still overlaps the other one.');
        } catch (CannotCreateOverlappingPlanningBlock) {
        }

        $this->bus()->dispatch($this->update(self::ROUSSEAU, endTime: '11:00'));
        $block = $this->blocks()->findById(PlanningBlockId::fromString(self::BLOCK));
        self::assertSame('11:00', $block?->timeRange()->endTime());
    }

    private function day(int $days): string
    {
        return new \DateTimeImmutable($this->anchor, new \DateTimeZone('UTC'))->modify(\sprintf('+%d days', $days))->format('Y-m-d');
    }

    private function create(string $staff, string $date, string $start, string $end, string $freq): CreatePlanningBlock
    {
        return new CreatePlanningBlock(
            clinicId: self::CLINIC,
            staffUserId: $staff,
            type: 'consultation',
            date: $date,
            startTime: $start,
            endTime: $end,
            capacityPerHour: 4,
            recurrenceFreq: $freq,
            recurrenceUntil: null,
            note: null,
        );
    }

    private function update(string $staff, string $endTime = '12:00', int $capacityPerHour = 4): UpdatePlanningBlock
    {
        return new UpdatePlanningBlock(
            blockId: self::BLOCK,
            clinicId: self::CLINIC,
            staffUserId: $staff,
            type: 'consultation',
            date: $this->anchor,
            startTime: '08:00',
            endTime: $endTime,
            capacityPerHour: $capacityPerHour,
            recurrenceFreq: 'WEEKLY',
            recurrenceUntil: null,
            note: null,
        );
    }

    private function block(string $id, string $date, string $start, string $end, RecurrenceRule $rule, string $staff = self::ROUSSEAU): PlanningBlock
    {
        return PlanningBlock::create(
            id: PlanningBlockId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            staffUserId: UserId::fromString($staff),
            type: PlanningBlockType::CONSULTATION,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: 4,
            rule: $rule,
            note: null,
        );
    }

    /**
     * @param int    $days      after the anchor
     * @param string $localTime Paris
     */
    private function appointment(string $practitioner, int $days, string $localTime, AppointmentStatus $status = AppointmentStatus::PLANNED): void
    {
        $paris = new \DateTimeImmutable($this->anchor . ' ' . $localTime, new \DateTimeZone('Europe/Paris'))->modify(\sprintf('+%d days', $days));

        AppointmentEntityFactory::new()->withClinicId(self::CLINIC)
            ->withPractitionerUserId($practitioner)
            ->startingAt($paris->setTimezone(new \DateTimeZone('UTC')))
            ->withStatus($status)
            ->create()
        ;
    }

    private function blocks(): PlanningBlockRepositoryInterface
    {
        $blocks = self::getContainer()->get(PlanningBlockRepositoryInterface::class);
        \assert($blocks instanceof PlanningBlockRepositoryInterface);

        return $blocks;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
