<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdatePlanningBlock;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Domain\Exception\CannotChangePlanningBlockTypeWithActiveAppointments;
use App\Context\Scheduling\Domain\Exception\CannotModifyRecurrenceRuleOnExistingBlock;
use App\Context\Scheduling\Domain\Exception\CannotReduceCapacityBelowExistingAppointmentCount;
use App\Context\Scheduling\Domain\Exception\CannotShrinkPlanningBlockBelowExistingAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePlanningBlockHandler
{
    public function __construct(
        private PlanningBlockRepositoryInterface $repository,
        private PlanningBlockOverlapCheckerInterface $overlapChecker,
        private PlanningBlockAppointmentCounterInterface $appointmentCounter,
        private ClinicTimezoneResolverInterface $timezoneResolver,
    ) {
    }

    public function __invoke(UpdatePlanningBlock $command): void
    {
        $blockId = PlanningBlockId::fromString($command->blockId);
        $block   = $this->repository->findById($blockId);

        if (null === $block) {
            throw new PlanningBlockNotFoundException(\sprintf('PlanningBlock "%s" not found.', $command->blockId));
        }

        $clinicId    = ClinicId::fromString($command->clinicId);
        $staffUserId = UserId::fromString($command->staffUserId);
        $newType     = PlanningBlockType::from($command->type);
        $newRange    = new TimeRange($command->date, $command->startTime, $command->endTime);
        $newRule     = $this->buildRecurrenceRule($command->recurrenceFreq, $command->recurrenceUntil);

        $this->overlapChecker->hasOverlap(
            $clinicId,
            $staffUserId,
            $command->date,
            $command->startTime,
            $command->endTime,
            $blockId,
        );

        $tz       = $this->timezoneResolver->resolveTimezone($clinicId);
        $startUtc = $newRange->toUtcStart($tz);
        $endUtc   = $newRange->toUtcEnd($tz);

        $count = $this->appointmentCounter->countActiveInWindow($clinicId, $staffUserId, $startUtc, $endUtc);

        // D.4 — recurrence rule immutable once set
        if ($newRule->freq() !== $block->recurrenceRule()->freq() || $newRule->until() !== $block->recurrenceRule()->until()) {
            throw new CannotModifyRecurrenceRuleOnExistingBlock(
                'Cannot modify recurrence rule on an existing planning block.'
            );
        }

        // D.1 — type change with active appointments
        if ($newType !== $block->type() && $count > 0) {
            throw new CannotChangePlanningBlockTypeWithActiveAppointments(
                'Cannot change block type while appointments exist in this window.'
            );
        }

        // D.2/D.5 — window shrunk or staff member changed with active appointments
        $rangeChanged = !$newRange->equals($block->timeRange());
        $staffChanged = !$staffUserId->equals($block->staffUserId());
        if (($rangeChanged || $staffChanged) && $count > 0) {
            throw new CannotShrinkPlanningBlockBelowExistingAppointments(
                'Cannot shrink block window or change staff member while appointments exist in this window.'
            );
        }

        // D.3 — capacity reduction below existing appointment count
        $slotDurationHours = $newRange->durationMinutes() / 60;
        $capacityForSlot   = (int) floor($command->capacityPerHour * $slotDurationHours);
        if ($capacityForSlot > 0 && $count > $capacityForSlot) {
            throw new CannotReduceCapacityBelowExistingAppointmentCount(
                'Cannot reduce capacity below the number of existing appointments in this window.'
            );
        }

        $block->update($newType, $newRange, $command->capacityPerHour, $newRule, $command->note);

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
