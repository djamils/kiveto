<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Applies one step of a simulated flow and schedules the next one. A flow
 * already advanced by hand (SimulatorDriver) is left as it is.
 */
#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class AdvanceSimulatedFlowHandler
{
    public function __construct(
        private SimulatedFlowStoreInterface $flows,
        private SimulationScheduler $scheduler,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdvanceSimulatedFlow $command): void
    {
        $flow = $this->flows->find($command->flowId);

        if (null === $flow || !$flow->advance($this->clock->now())) {
            return;
        }

        $this->flows->save($flow);
        $this->scheduler->scheduleNextStep($flow);
    }
}
