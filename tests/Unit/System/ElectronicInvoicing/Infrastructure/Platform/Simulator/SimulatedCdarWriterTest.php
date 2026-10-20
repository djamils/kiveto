<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cdar\CdarParser;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\DepositedDocument;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedCdarWriter;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedStatus;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use PHPUnit\Framework\TestCase;

final class SimulatedCdarWriterTest extends TestCase
{
    public function testRoundTripThroughTheParser(): void
    {
        $cii    = SimulatorKit::cii();
        $flow   = SimulatedFlow::deposit('flow-1', SimulatorKit::flow($cii), DepositedDocument::read($cii), SimulationScenario::NOMINAL, new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));
        $status = new SimulatedStatus(210, 'DOUBLON', new \DateTimeImmutable('2026-10-21T14:30:05+00:00'));

        $xml      = new SimulatedCdarWriter()->write($flow, $status);
        $messages = new CdarParser()->parse($xml);

        self::assertCount(1, $messages);
        self::assertSame(210, $messages[0]->statusCode);
        self::assertSame('DOUBLON', $messages[0]->reasonCode);
        self::assertSame('Facture en doublon (déjà émise / reçue)', $messages[0]->reasonText);
        self::assertSame('PAR-2026-000001', $messages[0]->invoiceNumber);
        self::assertSame('2026-10-21', $messages[0]->invoiceIssueDate?->format('Y-m-d'));
        self::assertSame('380', $messages[0]->typeCode);
        self::assertSame(SimulatorKit::SELLER, $messages[0]->sellerSiren);
        self::assertSame('2026-10-21T16:30:05+02:00', $messages[0]->issuedAt->format(\DATE_ATOM));
        self::assertStringContainsString('<ram:ProcessCondition>Refusée</ram:ProcessCondition>', $xml);
        self::assertStringContainsString('<udt:DateTimeString format="204">20261021163005</udt:DateTimeString>', $xml);
    }

    public function testUnknownReasonKeepsItsCodeAsText(): void
    {
        $flow = SimulatedFlow::deposit('flow-1', SimulatorKit::flow('<x/>'), DepositedDocument::read('<x/>'), SimulationScenario::NOMINAL, new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));

        $message = new CdarParser()->parse(new SimulatedCdarWriter()->write($flow, new SimulatedStatus(207, 'AUTRE', new \DateTimeImmutable('2026-10-21T10:00:00+00:00'))))[0];

        self::assertSame('AUTRE', $message->reasonText);
        self::assertNull($message->invoiceIssueDate);
        self::assertSame('tracking-1', $message->invoiceNumber);
    }

    public function testCodeOutsideTheTableIsWrittenWithoutLabel(): void
    {
        $flow = SimulatedFlow::deposit('flow-1', SimulatorKit::flow('<x/>'), DepositedDocument::read('<x/>'), SimulationScenario::NOMINAL, new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));
        $xml  = new SimulatedCdarWriter()->write($flow, new SimulatedStatus(299, null, new \DateTimeImmutable('2026-10-21T10:00:00+00:00')));

        self::assertStringNotContainsString('ProcessCondition>', str_replace('ProcessConditionCode>', '', $xml));

        $this->expectException(UnparsableLifecycleMessageException::class);
        new CdarParser()->parse($xml);
    }
}
