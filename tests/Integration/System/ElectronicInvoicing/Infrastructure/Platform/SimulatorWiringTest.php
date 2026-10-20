<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\ElectronicInvoicing\Infrastructure\Platform;

use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\AdvanceSimulatedFlow;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

final class SimulatorWiringTest extends KernelTestCase
{
    public function testTheSimulatorIsTheTestPlatform(): void
    {
        $directory = self::getContainer()->get(DirectoryLookupInterface::class);
        \assert($directory instanceof DirectoryLookupInterface);

        self::assertFalse($directory->lookup('000000000')->reachable);
        self::assertSame('0225', $directory->lookup('523456788')->scheme);
        self::assertSame(0, self::getContainer()->getParameter('electronic_invoicing.simulator.step_delay'));
    }

    public function testStepsAreSentToTheAsyncTransportAndHandledOnTheCommandBus(): void
    {
        $senders = self::getContainer()->get('messenger.senders_locator');
        \assert($senders instanceof SendersLocatorInterface);
        $handlers = self::getContainer()->get('messenger.bus.command.messenger.handlers_locator');
        \assert($handlers instanceof HandlersLocatorInterface);

        $envelope = new Envelope(new AdvanceSimulatedFlow('flow'));

        self::assertSame(['async'], array_map(strval(...), array_keys(iterator_to_array($senders->getSenders($envelope)))));
        self::assertCount(1, iterator_to_array($handlers->getHandlers($envelope), false));
    }
}
