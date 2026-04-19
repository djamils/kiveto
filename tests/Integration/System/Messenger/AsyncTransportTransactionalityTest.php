<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Application\Bus\IntegrationEventBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * B11 / AC14b — Determines whether async transport inserts roll back with an
 * explicit DBAL transaction (proxy for the doctrine_transaction middleware behavior)
 * or commit independently.
 *
 * Records a verdict via markTestIncomplete so the reviewer can reconcile D30
 * before approving PR2.
 *
 * VERDICT recorded in the test output:
 * - "at-least-once": shared__messenger_messages insert rolls back → D30 debt vanishes.
 * - "at-most-once": insert commits independently → D30 debt stands.
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

        if (0 === $newRows) {
            self::markTestIncomplete(
                'VERDICT: at-least-once cross-BC. '
                . 'The async transport insert rolled back with the explicit DBAL transaction. '
                . 'D30 debt effectively vanishes — no outbox needed. '
                . 'Remove "log-and-pray" language from the spec and update D30 accordingly.',
            );
        } else {
            self::markTestIncomplete(
                "VERDICT: at-most-once cross-BC. {$newRows} row(s) remain in shared__messenger_messages "
                . 'even after the outer DBAL transaction was rolled back. '
                . 'D30 debt stands — keep log-a-warning-on-publish-failure stance. '
                . 'Open a GitHub issue to track the outbox pattern as future work.',
            );
        }
    }

    private function countMessengerRows(Connection $connection): int
    {
        $count = $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', self::MESSENGER_TABLE));
        \assert(\is_int($count) || \is_string($count) || false === $count);

        return false === $count ? 0 : (int) $count;
    }
}
