<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\DepositedDocument;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use PHPUnit\Framework\TestCase;

final class SimulatedFlowTest extends TestCase
{
    public function testStatusesGetStrictlyIncreasingSecondTimestampsInUtc(): void
    {
        $now  = new \DateTimeImmutable('2026-10-21 12:00:00.750000', new \DateTimeZone('Europe/Paris'));
        $flow = SimulatedFlow::deposit('flow-1', SimulatorKit::flow('<x/>'), DepositedDocument::read('<x/>'), SimulationScenario::NOMINAL, $now);

        self::assertTrue($flow->advance($now));
        $flow->emit(212, 'PAYE', $now->modify('-1 hour'));

        self::assertSame(
            ['2026-10-21T10:00:00+00:00', '2026-10-21T10:00:01+00:00', '2026-10-21T10:00:02+00:00'],
            array_map(static fn (SimulatedStatus $s): string => $s->at->format(\DATE_ATOM), $flow->statuses()),
        );
        self::assertSame('2026-10-21T10:00:00+00:00', $flow->createdAt->format(\DATE_ATOM));
        self::assertSame('2026-10-21T10:00:02+00:00', $flow->updatedAt()->format(\DATE_ATOM));
        self::assertSame(2, $flow->nextStep());
    }

    public function testScenarioEndsAndNothingMoreIsEmitted(): void
    {
        $now  = new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
        $flow = SimulatedFlow::deposit('flow-1', SimulatorKit::flow('<x/>'), DepositedDocument::read('<x/>'), SimulationScenario::REJECTED, $now);

        self::assertTrue($flow->isPending());
        self::assertTrue($flow->advance($now));
        self::assertFalse($flow->isPending());
        self::assertNull($flow->nextStep());
        self::assertFalse($flow->advance($now));
        self::assertCount(2, $flow->statuses());
    }

    public function testStatusFlowIds(): void
    {
        $now  = new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
        $flow = SimulatedFlow::deposit('flow-1', SimulatorKit::flow('<x/>'), DepositedDocument::read('<x/>'), SimulationScenario::NOMINAL, $now);

        self::assertSame('flow-1-01', $flow->statusFlowId(0));
        self::assertSame(200, $flow->statusFor('flow-1-01')?->code);
        self::assertNull($flow->statusFor('flow-1-02'));
    }

    public function testUpdatedAtDefaultsToCreation(): void
    {
        $flow = new SimulatedFlow('f', '812456903', 't', 's', 'N', '380', null, '812456903', null, SimulationScenario::NOMINAL, '', new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));

        self::assertSame('2026-10-21T10:00:00+00:00', $flow->updatedAt()->format(\DATE_ATOM));
        self::assertSame([], $flow->statuses());
    }

    public function testStatusSerialisation(): void
    {
        $status = new SimulatedStatus(210, 'DOUBLON', new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));

        self::assertSame(['code' => 210, 'reason' => 'DOUBLON', 'at' => '2026-10-21T10:00:00+00:00'], $status->toArray());
        self::assertSame($status->toArray(), SimulatedStatus::fromArray($status->toArray())->toArray());
    }
}
