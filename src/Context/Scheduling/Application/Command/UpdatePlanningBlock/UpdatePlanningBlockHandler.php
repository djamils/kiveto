<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdatePlanningBlock;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\BlockAppointments;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Exception\CannotChangePlanningBlockTypeWithActiveAppointments;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
use App\Context\Scheduling\Domain\Exception\CannotModifyRecurrenceRuleOnExistingBlock;
use App\Context\Scheduling\Domain\Exception\CannotReduceCapacityBelowExistingAppointmentCount;
use App\Context\Scheduling\Domain\Exception\CannotShrinkPlanningBlockBelowExistingAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\Service\BlockCapacity;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\Service\PlanningBlockOccurrences;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePlanningBlockHandler
{
    public function __construct(
        private PlanningBlockRepositoryInterface $repository,
        private PlanningBlockOverlapCheckerInterface $overlapChecker,
        private OccupyingAppointmentsReaderInterface $appointmentsReader,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdatePlanningBlock $command): void
    {
        $blockId = PlanningBlockId::fromString($command->blockId);
        $block   = $this->repository->findById($blockId);

        $clinicId = ClinicId::fromString($command->clinicId);

        // A block of another clinic is reported as missing, never exposed
        if (null === $block || !$block->clinicId()->equals($clinicId)) {
            throw new PlanningBlockNotFoundException(\sprintf('PlanningBlock "%s" not found.', $command->blockId));
        }

        $staffUserId = UserId::fromString($command->staffUserId);
        $newType     = PlanningBlockType::from($command->type);
        $newRange    = new TimeRange($command->date, $command->startTime, $command->endTime);
        $newRule     = $this->buildRecurrenceRule($command->recurrenceFreq, $command->recurrenceUntil);

        $tz    = $this->timezoneResolver->resolveTimezone($clinicId);
        $today = $this->clock->now()->setTimezone($tz)->format('Y-m-d');

        // The days the block leaves and the days it takes: the counts below are current
        $this->dayLock->lock($clinicId, LockKeys::merge(
            LockKeys::forBlock($block->staffUserId(), $block->timeRange(), $block->recurrenceRule(), $today),
            LockKeys::forBlock($staffUserId, $newRange, $newRule, $today),
        ));

        // Same rule as a creation: no two blocks of a staff member overlap. Checked only when the
        // block moves (staff member, date or times): an update of another field leaves an overlap
        // that predates the occurrence-by-occurrence check as it is, and stays possible
        $moved = !$staffUserId->equals($block->staffUserId()) || !$newRange->equals($block->timeRange());

        if ($moved && $this->overlapChecker->hasOverlap($clinicId, $staffUserId, $newRange, $newRule, $today, $blockId)) {
            throw new CannotCreateOverlappingPlanningBlock(\sprintf(
                'Planning block overlaps with an existing block for staff member "%s" on %s.',
                $command->staffUserId,
                $command->date,
            ));
        }

        // D.4 — recurrence rule immutable once set
        $ruleChanged = $newRule->freq() !== $block->recurrenceRule()->freq()
            || $newRule->until() !== $block->recurrenceRule()->until();

        if ($ruleChanged) {
            throw new CannotModifyRecurrenceRuleOnExistingBlock(
                'Cannot modify recurrence rule on an existing planning block.'
            );
        }

        $current = new PlanningBlockOccurrences($block->timeRange(), $block->recurrenceRule(), $tz, $today);
        $next    = new PlanningBlockOccurrences($newRange, $newRule, $tz, $today);

        // What the block holds now, in every future occurrence: the original practitioner's appointments
        $held = $current->held(BlockAppointments::read($this->appointmentsReader, $clinicId, $block->staffUserId(), $current));

        // D.1 — type change with active appointments
        if ($newType !== $block->type() && [] !== $held) {
            throw new CannotChangePlanningBlockTypeWithActiveAppointments(
                'Cannot change block type while appointments exist in this window.'
            );
        }

        // D.2/D.5 — an appointment would lose its block: staff member changed (the original
        // practitioner's appointments), or window moved or shrunk past it — one inside the
        // block must stay inside, one straddling its edge must still meet it
        $staffChanged = !$staffUserId->equals($block->staffUserId());
        $orphaned     = $staffChanged
            ? $held
            : array_filter($held, static fn (BusyInterval $appointment): bool => $current->contains($appointment)
                ? !$next->contains($appointment)
                : [] === $next->held([$appointment]));

        if ([] !== $orphaned) {
            throw new CannotShrinkPlanningBlockBelowExistingAppointments(
                'Cannot shrink block window or change staff member while appointments exist in this window.'
            );
        }

        // D.3 — capacity reduction below the appointments of an occurrence
        $capacityForSlot = BlockCapacity::of($command->capacityPerHour, $newRange->durationMinutes());
        if ($capacityForSlot > 0) {
            $busiest = $next->busiestOccurrence(BlockAppointments::read($this->appointmentsReader, $clinicId, $staffUserId, $next));

            if ($busiest > $capacityForSlot) {
                throw new CannotReduceCapacityBelowExistingAppointmentCount(
                    'Cannot reduce capacity below the number of existing appointments in this window.'
                );
            }
        }

        $block->update($staffUserId, $newType, $newRange, $command->capacityPerHour, $newRule, $command->note);

        $this->repository->save($block);
    }

    private function buildRecurrenceRule(string $freq, ?string $until): RecurrenceRule
    {
        return match (strtoupper($freq)) {
            RecurrenceRule::DAILY    => RecurrenceRule::daily($until),
            RecurrenceRule::WEEKLY   => RecurrenceRule::weekly($until),
            RecurrenceRule::WEEKDAYS => RecurrenceRule::weekdays($until),
            default                  => RecurrenceRule::none(),
        };
    }
}
