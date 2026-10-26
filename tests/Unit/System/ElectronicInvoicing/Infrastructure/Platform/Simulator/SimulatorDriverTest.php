<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Application\Port\ConfiguredPlatformInterface;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriver;
use PHPUnit\Framework\TestCase;

final class SimulatorDriverTest extends TestCase
{
    private InMemorySimulatedFlowStore $store;
    private SettableClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemorySimulatedFlowStore();
        $this->clock = new SettableClock();
    }

    public static function platform(?string $code): ConfiguredPlatformInterface
    {
        return new readonly class($code) implements ConfiguredPlatformInterface {
            public function __construct(private ?string $code)
            {
            }

            public function code(): ?string
            {
                return $this->code;
            }
        };
    }

    public function testAdvanceAllAppliesEveryPendingStep(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $nominal   = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(), 'tracking-1'))->flowId;
        $rejected  = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii('000002139', 'PAR-2026-000002'), 'tracking-2'))->flowId;

        self::assertSame(4, $this->driver()->advanceAll());

        self::assertSame([200, 202, 203, 205], $this->codes($nominal));
        self::assertSame([200, 213], $this->codes($rejected));
        self::assertSame([], $this->store->pending());
        self::assertSame(0, $this->driver()->advanceAll());
    }

    public function testAdvanceAllStopsAfterMaxSteps(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $first     = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(), 'tracking-1'))->flowId;
        $second    = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(number: 'PAR-2026-000002'), 'tracking-2'))->flowId;

        self::assertSame(2, $this->driver()->advanceAll(2));
        self::assertSame([200, 202, 203], $this->codes($first));
        self::assertSame([200], $this->codes($second));

        self::assertSame(3, $this->driver()->advanceAll(3));
        self::assertSame([200, 202, 203, 205], $this->codes($first));
        self::assertSame([200, 202, 203], $this->codes($second));
    }

    public function testEmitAppendsAStatusToTheDocumentFlow(): void
    {
        $flowId = SimulatorKit::simulator($this->store, $this->clock)->submit(SimulatorKit::flow(SimulatorKit::cii()))->flowId;

        $status = $this->driver()->emit(SimulatorKit::SELLER, 'PAR-2026-000001', 212, 'PAYE');

        self::assertSame(212, $status->code);
        self::assertSame('PAYE', $status->reason);
        self::assertSame([200, 212], $this->codes($flowId));
    }

    public function testEmitRefusesUnknownCodesAndDocuments(): void
    {
        SimulatorKit::simulator($this->store, $this->clock)->submit(SimulatorKit::flow(SimulatorKit::cii()));

        try {
            $this->driver()->emit(SimulatorKit::SELLER, 'PAR-2026-000001', 299, null);
            self::fail('Unknown code expected');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Unknown lifecycle status code 299.', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No simulated flow for document NOPE of seller ' . SimulatorKit::SELLER . '.');
        $this->driver()->emit(SimulatorKit::SELLER, 'NOPE', 205, null);
    }

    public function testRefusedUnlessThePlatformIsTheSimulator(): void
    {
        foreach (['advanceAll' => [], 'emit' => [SimulatorKit::SELLER, 'X', 205, null]] as $method => $arguments) {
            foreach ([null, 'other'] as $code) {
                try {
                    $driver = new SimulatorDriver($this->store, self::platform($code), $this->clock);
                    $driver->{$method}(...$arguments);
                    self::fail('Refusal expected');
                } catch (\LogicException $e) {
                    self::assertSame('The configured e-invoicing platform is not the simulator.', $e->getMessage());
                }
            }
        }
    }

    private function driver(): SimulatorDriver
    {
        return new SimulatorDriver($this->store, self::platform('simulator'), $this->clock);
    }

    /**
     * @return list<int>
     */
    private function codes(string $flowId): array
    {
        $flow = $this->store->find($flowId);
        self::assertNotNull($flow);

        return array_map(static fn (SimulatedStatus $s): int => $s->code, $flow->statuses());
    }
}
