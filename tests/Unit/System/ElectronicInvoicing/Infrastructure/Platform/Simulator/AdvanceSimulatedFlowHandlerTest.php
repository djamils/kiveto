<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\AdvanceSimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\AdvanceSimulatedFlowHandler;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScheduler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AdvanceSimulatedFlowHandlerTest extends TestCase
{
    public function testAppliesOneStepAndSchedulesTheNextOneUntilTheScenarioEnds(): void
    {
        $store   = new InMemorySimulatedFlowStore();
        $clock   = new SettableClock();
        $bus     = new CapturingMessageBus();
        $flowId  = SimulatorKit::simulator($store, $clock, new CapturingMessageBus())->submit(SimulatorKit::flow(SimulatorKit::cii('000002139')))->flowId;
        $handler = new AdvanceSimulatedFlowHandler($store, new SimulationScheduler($bus, 30000), $clock);

        $handler(new AdvanceSimulatedFlow($flowId));

        $flow = $store->find($flowId);
        self::assertNotNull($flow);
        self::assertSame([200, 213], array_map(static fn (SimulatedStatus $s): int => $s->code, $flow->statuses()));
        // Rejected ends at 213: nothing more to schedule.
        self::assertSame([], $bus->envelopes);

        $handler(new AdvanceSimulatedFlow($flowId));
        $handler(new AdvanceSimulatedFlow('unknown'));
        $flow = $store->find($flowId);
        self::assertNotNull($flow);
        self::assertCount(2, $flow->statuses());
    }

    public function testSchedulesTheNextStepWithTheConfiguredDelay(): void
    {
        $store   = new InMemorySimulatedFlowStore();
        $clock   = new SettableClock();
        $bus     = new CapturingMessageBus();
        $flowId  = SimulatorKit::simulator($store, $clock)->submit(SimulatorKit::flow(SimulatorKit::cii()))->flowId;
        $handler = new AdvanceSimulatedFlowHandler($store, new SimulationScheduler($bus, 30000), $clock);

        $handler(new AdvanceSimulatedFlow($flowId));

        self::assertCount(1, $bus->envelopes);
        $message = $bus->envelopes[0]->getMessage();
        self::assertInstanceOf(AdvanceSimulatedFlow::class, $message);
        self::assertSame($flowId, $message->flowId);
        self::assertSame(30000, $bus->envelopes[0]->last(DelayStamp::class)?->getDelay());
    }
}
