<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Tests\Integration\System\Messenger\Probe\ProbeExternalApiCommand;
use App\Tests\Integration\System\Messenger\Probe\ProbeExternalApiCommandHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * External-call commands are queued in the caller's transaction (implicit
 * outbox) on their own queues, and the external worker drains the interactive
 * queue before the batch one. DAMA wraps every test in a transaction, so the
 * explicit transactions below are savepoints.
 */
final class ExternalApiCommandOutboxTest extends KernelTestCase
{
    public function testACommittedSendLeavesOneRowOnTheExternalQueueOnly(): void
    {
        $connection = $this->connection();
        $default    = $this->rows($connection, 'default');

        $connection->beginTransaction();
        $this->bus()->send(new ProbeExternalApiCommand('committed'));
        $connection->commit();

        self::assertSame(1, $this->rows($connection, 'external', 'committed'));
        self::assertSame(0, $this->rows($connection, 'external_batch', 'committed'));
        self::assertSame($default, $this->rows($connection, 'default'));
    }

    public function testARolledBackSendLeavesNoRow(): void
    {
        $connection = $this->connection();

        $connection->beginTransaction();
        $this->bus()->send(new ProbeExternalApiCommand('rolled-back'));
        $connection->rollBack();

        self::assertSame(0, $this->rows($connection, 'external', 'rolled-back'));
    }

    public function testABatchSendGoesToTheBatchQueue(): void
    {
        $connection = $this->connection();

        $this->bus()->send(new ProbeExternalApiCommand('batch'), new TransportNamesStamp(['async_external_batch']));

        self::assertSame(1, $this->rows($connection, 'external_batch', 'batch'));
        self::assertSame(0, $this->rows($connection, 'external', 'batch'));
    }

    public function testTheCommandBusRefusesAnExternalApiCommand(): void
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        $this->expectException(\LogicException::class);

        $bus->dispatch(new ProbeExternalApiCommand('refused'));
    }

    public function testTheWorkerHandlesTheInteractiveQueueFirst(): void
    {
        $container = self::getContainer();

        $this->bus()->send(new ProbeExternalApiCommand('batch-1'), new TransportNamesStamp(['async_external_batch']));
        $this->bus()->send(new ProbeExternalApiCommand('batch-2'), new TransportNamesStamp(['async_external_batch']));
        $this->bus()->send(new ProbeExternalApiCommand('interactive'));

        $receivers = [];

        foreach (['async_external', 'async_external_batch'] as $name) {
            $receiver = $container->get('messenger.transport.' . $name);
            \assert($receiver instanceof ReceiverInterface);
            $receivers[$name] = $receiver;
        }

        $routableBus = $container->get('messenger.routable_message_bus');
        \assert($routableBus instanceof MessageBusInterface);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));

        new Worker($receivers, $routableBus, $dispatcher)->run(['sleep' => 0]);

        $handler = $container->get(ProbeExternalApiCommandHandler::class);
        \assert($handler instanceof ProbeExternalApiCommandHandler);

        self::assertSame(['interactive', 'batch-1', 'batch-2'], $handler->handled());
        // On MySQL an acked row is only marked delivered (and purged on a later get()).
        $pending = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name LIKE \'external%\' AND delivered_at IS NULL',
        );
        self::assertSame(0, (int) (\is_scalar($pending) ? $pending : 1));
    }

    private function bus(): ExternalApiCommandBusInterface
    {
        $bus = self::getContainer()->get(ExternalApiCommandBusInterface::class);
        \assert($bus instanceof ExternalApiCommandBusInterface);

        return $bus;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }

    private function rows(Connection $connection, string $queue, ?string $marker = null): int
    {
        $sql    = 'SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name = :queue';
        $params = ['queue' => $queue];

        if (null !== $marker) {
            $sql .= ' AND body LIKE :marker';
            $params['marker'] = '%ProbeExternalApiCommand%' . $marker . '%';
        }

        $count = $connection->fetchOne($sql, $params);

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
