<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Integration\System\Messenger\Probe\OutboxProbeCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves the implicit outbox: the integration-event row in the Doctrine transport is
 * committed or rolled back together with the business data of the command.
 */
final class CommandBusOutboxAtomicityTest extends KernelTestCase
{
    private const string CLINIC_ID = '0199f2a0-0000-7000-8000-00000000c1c1';

    public function testFailureAfterFlushAndPublishRollsBackBusinessRowAndMessage(): void
    {
        $clientId   = '0199f2a0-0000-7000-8000-0000000000a1';
        $connection = $this->connection();
        $messages   = $this->countProbeMessages($connection);

        try {
            $this->commandBus()->dispatch(
                new OutboxProbeCommand(self::CLINIC_ID, $clientId, OutboxProbeCommand::FAIL_AFTER_PUBLISH),
            );
            self::fail('The probe command was expected to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Outbox probe failure after publish.', $exception->getMessage());
        }

        self::assertSame(0, $this->countClients($connection, $clientId));
        self::assertSame($messages, $this->countProbeMessages($connection));
    }

    public function testSuccessCommitsBusinessRowAndMessage(): void
    {
        $clientId   = '0199f2a0-0000-7000-8000-0000000000a2';
        $connection = $this->connection();
        $messages   = $this->countProbeMessages($connection);

        $this->commandBus()->dispatch(new OutboxProbeCommand(self::CLINIC_ID, $clientId));

        self::assertSame(1, $this->countClients($connection, $clientId));
        self::assertSame($messages + 1, $this->countProbeMessages($connection));
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }

    private function countClients(Connection $connection, string $clientId): int
    {
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM client__clients WHERE id = UNHEX(REPLACE(:id, \'-\', \'\'))',
            ['id' => $clientId],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }

    private function countProbeMessages(Connection $connection): int
    {
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE :probe',
            ['probe' => '%TransactionalityProbeIntegrationEvent%'],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
