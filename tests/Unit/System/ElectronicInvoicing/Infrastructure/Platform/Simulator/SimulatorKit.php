<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cii\CiiInvoiceRenderer;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\LocalPlatformSimulator;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedCdarWriter;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlowStoreInterface;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScheduler;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\AfnorSamplesXsdTest;
use App\Tests\Unit\System\ElectronicInvoicing\Support\TransmittableInvoices;

/**
 * Builds the local platform simulator over an in-memory store for unit tests.
 */
final class SimulatorKit
{
    public const string SELLER = TransmittableInvoices::SELLER;

    public static function uuids(): UuidGeneratorInterface
    {
        return new class implements UuidGeneratorInterface {
            private int $next = 0;

            public function generate(): string
            {
                return \sprintf('0193a000-0000-7000-8000-%012d', ++$this->next);
            }
        };
    }

    public static function simulator(SimulatedFlowStoreInterface $store, ClockInterface $clock, ?CapturingMessageBus $bus = null, ?UuidGeneratorInterface $uuids = null): LocalPlatformSimulator
    {
        return new LocalPlatformSimulator(
            $store,
            new XsdValidator(),
            AfnorSamplesXsdTest::SCHEMA,
            new SimulatedCdarWriter(),
            new SimulationScheduler($bus ?? new CapturingMessageBus(), 0),
            $uuids ?? self::uuids(),
            $clock,
        );
    }

    /**
     * A valid CII invoice of SELLER to $buyerSiren.
     */
    public static function cii(string $buyerSiren = TransmittableInvoices::BUYER, string $number = 'PAR-2026-000001', bool $creditNote = false): string
    {
        $renderer = new CiiInvoiceRenderer(new XsdValidator(), AfnorSamplesXsdTest::SCHEMA);
        $document = $creditNote
            ? TransmittableInvoices::creditNote(number: $number)
            : TransmittableInvoices::invoice(number: $number, buyerSiren: $buyerSiren);

        return $renderer->render($document, new TransmittableElectronicAddress('0225', $buyerSiren));
    }

    public static function flow(string $content, string $trackingId = 'tracking-1', string $organisation = self::SELLER): OutboundFlow
    {
        return new OutboundFlow($organisation, $trackingId, $trackingId . '.xml', $content, hash('sha256', $content), 'CII', 'B2B');
    }
}
