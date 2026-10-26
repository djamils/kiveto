<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Console;

use App\System\ElectronicInvoicing\Infrastructure\Console\SimulateStatusCommand;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriver;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\InMemorySimulatedFlowStore;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SettableClock;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriverTest;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SimulateStatusCommandTest extends TestCase
{
    private InMemorySimulatedFlowStore $store;
    private SettableClock $clock;
    private string $flowId;

    protected function setUp(): void
    {
        $this->store  = new InMemorySimulatedFlowStore();
        $this->clock  = new SettableClock();
        $this->flowId = SimulatorKit::simulator($this->store, $this->clock)->submit(SimulatorKit::flow(SimulatorKit::cii()))->flowId;
    }

    public function testEmitsAStatusWithItsReason(): void
    {
        $tester = $this->tester();

        $exit = $tester->execute(['sellerSiren' => SimulatorKit::SELLER, 'documentNumber' => 'PAR-2026-000001', 'statusCode' => '210', '--reason' => 'DOUBLON']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Status 210 emitted on PAR-2026-000001', $tester->getDisplay());
        self::assertSame([[200, null], [210, 'DOUBLON']], $this->statuses());
    }

    public function testEmitsAStatusWithoutReason(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester()->execute(['sellerSiren' => SimulatorKit::SELLER, 'documentNumber' => 'PAR-2026-000001', 'statusCode' => '205']));
        self::assertSame([[200, null], [205, null]], $this->statuses());
    }

    public function testAdvanceAll(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--advance-all' => true]));
        self::assertStringContainsString('3 status(es) emitted.', $tester->getDisplay());
        self::assertCount(4, $this->statuses());
    }

    public function testMissingOrMalformedArgumentsAreInvalid(): void
    {
        foreach ([[], ['sellerSiren' => SimulatorKit::SELLER, 'documentNumber' => 'PAR-2026-000001', 'statusCode' => 'OK']] as $input) {
            $tester = $this->tester();

            self::assertSame(Command::INVALID, $tester->execute($input));
            self::assertStringContainsString('Usage:', $tester->getDisplay());
        }
    }

    public function testUnknownDocumentFails(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['sellerSiren' => SimulatorKit::SELLER, 'documentNumber' => 'NOPE', 'statusCode' => '205']));
        self::assertStringContainsString('No simulated flow', $tester->getDisplay());
    }

    public function testRefusedOutsideTheSimulator(): void
    {
        $tester = new CommandTester(new SimulateStatusCommand(new SimulatorDriver($this->store, SimulatorDriverTest::platform(null), $this->clock)));

        self::assertSame(Command::FAILURE, $tester->execute(['--advance-all' => true]));
        self::assertStringContainsString('not the simulator', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new SimulateStatusCommand(new SimulatorDriver($this->store, SimulatorDriverTest::platform('simulator'), $this->clock)));
    }

    /**
     * @return list<array{int, ?string}>
     */
    private function statuses(): array
    {
        $flow = $this->store->find($this->flowId);
        self::assertNotNull($flow);

        return array_map(static fn (SimulatedStatus $s): array => [$s->code, $s->reason], $flow->statuses());
    }
}
