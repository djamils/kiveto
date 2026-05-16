<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Application\Bus\IntegrationEventBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * B11 / AC14b — Guards the confirmed "at-least-once" transactionality verdict.
 *
 * Verdict (confirmed): the async transport INSERT rolls back with an explicit
 * DBAL transaction. D30 debt vanishes — no outbox needed.
 *
 * This test exists as a regression guard: if a messenger/transport config change
 * ever breaks the rollback behaviour, this test will fail and surface the risk.
 */
final class AsyncTransportTransactionalityTest extends KernelTestCase
{
    use Factories;

    private const string MESSENGER_TABLE = 'shared__messenger_messages';

    public function testIntegrationEventTransportRollsBackWithExplicitTransaction(): void
    {
        $container = self::getContainer();

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);

        $bus = $container->get(IntegrationEventBusInterface::class);
        \assert($bus instanceof IntegrationEventBusInterface);

        $rowsBefore = $this->countMessengerRows($connection);

        $connection->beginTransaction();

        try {
            $bus->publish([], new TransactionalityProbeIntegrationEvent());
            $connection->rollBack();
        } catch (\Throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw new \RuntimeException('Unexpected error during probe dispatch — check messenger config.');
        }

        $rowsAfter = $this->countMessengerRows($connection);
        $newRows   = $rowsAfter - $rowsBefore;

        self::assertSame(
            0,
            $newRows,
            'Async transport insert must roll back with an explicit DBAL transaction (at-least-once guarantee). '
            . 'If this fails, a messenger/transport config change broke transactionality — review D30.',
        );
    }

    private function countMessengerRows(Connection $connection): int
    {
        $count = $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', self::MESSENGER_TABLE));
        \assert(\is_int($count) || \is_string($count) || false === $count);

        return false === $count ? 0 : (int) $count;
    }
}
