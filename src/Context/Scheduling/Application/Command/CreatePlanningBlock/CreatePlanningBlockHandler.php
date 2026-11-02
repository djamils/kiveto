<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CreatePlanningBlock;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockOverlapCheckerInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\Exception\CannotCreateOverlappingPlanningBlock;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePlanningBlockHandler
{
    public function __construct(
        private PlanningBlockRepositoryInterface $repository,
        private PlanningBlockOverlapCheckerInterface $overlapChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreatePlanningBlock $command): string
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $staffUserId = UserId::fromString($command->staffUserId);
        $type        = PlanningBlockType::from($command->type);
        $timeRange   = new TimeRange($command->date, $command->startTime, $command->endTime);
        $rule        = $this->buildRecurrenceRule($command->recurrenceFreq, $command->recurrenceUntil);

        // A booking in flight on those days sees the new block or is not written yet (AC 149)
        $today = $this->clock->now()->setTimezone($this->timezoneResolver->resolveTimezone($clinicId))->format('Y-m-d');
        $this->dayLock->lock($clinicId, LockKeys::forBlock($staffUserId, $timeRange, $rule, $today));

        // Occurrence by occurrence: a recurring block meets the blocks of every date it occurs on
        $hasOverlap = $this->overlapChecker->hasOverlap($clinicId, $staffUserId, $timeRange, $rule, $today);

        if ($hasOverlap) {
            throw new CannotCreateOverlappingPlanningBlock(\sprintf(
                'Planning block overlaps with an existing block for staff member "%s" on %s.',
                $command->staffUserId,
                $command->date,
            ));
        }

        $id    = PlanningBlockId::fromString($this->uuidGenerator->generate());
        $block = PlanningBlock::create(
            id: $id,
            clinicId: $clinicId,
            staffUserId: $staffUserId,
            type: $type,
            timeRange: $timeRange,
            capacityPerHour: $command->capacityPerHour,
            rule: $rule,
            note: $command->note,
        );

        $this->repository->save($block);

        return $id->toString();
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
