<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Schedules the next step of a simulated flow after the configured delay
 * (%electronic_invoicing.simulator.step_delay%, in milliseconds).
 */
final readonly class SimulationScheduler
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private int $stepDelay,
    ) {
    }

    public function scheduleNextStep(SimulatedFlow $flow): void
    {
        if ($flow->isPending()) {
            $this->commandBus->dispatch(new AdvanceSimulatedFlow($flow->flowId), [new DelayStamp($this->stepDelay)]);
        }
    }
}
