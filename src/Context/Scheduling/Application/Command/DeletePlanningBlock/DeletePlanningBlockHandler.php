<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeletePlanningBlock;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\BlockAppointments;
use App\Context\Scheduling\Domain\Exception\CannotDeletePlanningBlockWithAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\Service\PlanningBlockOccurrences;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeletePlanningBlockHandler
{
    public function __construct(
        private PlanningBlockRepositoryInterface $repository,
        private OccupyingAppointmentsReaderInterface $appointmentsReader,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeletePlanningBlock $command): void
    {
        $blockId = PlanningBlockId::fromString($command->blockId);
        $block   = $this->repository->findById($blockId);

        $clinicId = ClinicId::fromString($command->clinicId);

        // A block of another clinic is reported as missing, never exposed
        if (null === $block || !$block->clinicId()->equals($clinicId)) {
            throw new PlanningBlockNotFoundException(\sprintf('PlanningBlock "%s" not found.', $command->blockId));
        }

        $tz = $this->timezoneResolver->resolveTimezone($clinicId);

        // A booking in flight on those days is written first or sees the block gone (AC 149)
        $today = $this->clock->now()->setTimezone($tz)->format('Y-m-d');
        $this->dayLock->lock($clinicId, LockKeys::forBlock($block->staffUserId(), $block->timeRange(), $block->recurrenceRule(), $today));

        // Every future occurrence counts, not only the first one: no appointment may lose its block
        $occurrences = new PlanningBlockOccurrences($block->timeRange(), $block->recurrenceRule(), $tz, $today);
        $held        = $occurrences->held(BlockAppointments::read($this->appointmentsReader, $clinicId, $block->staffUserId(), $occurrences));

        if ([] !== $held) {
            throw new CannotDeletePlanningBlockWithAppointments(
                'Cannot delete a planning block that has active appointments.'
            );
        }

        $block->delete();
        $this->repository->delete($block);
    }
}
