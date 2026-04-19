<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeletePlanningBlock;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockAppointmentCounterInterface;
use App\Context\Scheduling\Domain\Exception\CannotDeletePlanningBlockWithAppointments;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeletePlanningBlockHandler
{
    public function __construct(
        private PlanningBlockRepositoryInterface $repository,
        private PlanningBlockAppointmentCounterInterface $appointmentCounter,
        private ClinicTimezoneResolverInterface $timezoneResolver,
    ) {
    }

    public function __invoke(DeletePlanningBlock $command): void
    {
        $blockId = PlanningBlockId::fromString($command->blockId);
        $block   = $this->repository->findById($blockId);

        if (null === $block) {
            throw new PlanningBlockNotFoundException(\sprintf('PlanningBlock "%s" not found.', $command->blockId));
        }

        $clinicId = ClinicId::fromString($command->clinicId);
        $tz       = $this->timezoneResolver->resolveTimezone($clinicId);
        $startUtc = $block->timeRange()->toUtcStart($tz);
        $endUtc   = $block->timeRange()->toUtcEnd($tz);

        $count = $this->appointmentCounter->countActiveInWindow($clinicId, $block->practitionerId(), $startUtc, $endUtc);

        if ($count > 0) {
            throw new CannotDeletePlanningBlockWithAppointments(
                'Cannot delete a planning block that has active appointments.'
            );
        }

        $block->delete();
        $this->repository->delete($block);
    }
}
