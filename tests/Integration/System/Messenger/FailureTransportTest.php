<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FailureTransportTest extends KernelTestCase
{
    public function testFailedMessagesAreStoredInTheMessengerTableUnderTheFailedQueue(): void
    {
        $container = self::getContainer();

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);

        $transport = $container->get('messenger.transport.failed');
        \assert($transport instanceof TransportInterface);

        $before       = $this->countFailedRows($connection);
        $legacyBefore = $this->legacyTableExists($connection);

        $transport->send(new Envelope(new TransactionalityProbeIntegrationEvent()));

        self::assertSame($before + 1, $this->countFailedRows($connection));

        $received = [...$transport->get()];
        self::assertCount(1, $received);
        self::assertInstanceOf(TransactionalityProbeIntegrationEvent::class, $received[0]->getMessage());

        // No table is created at runtime (auto_setup is off on every Doctrine transport).
        self::assertSame($legacyBefore, $this->legacyTableExists($connection));
    }

    private function legacyTableExists(Connection $connection): bool
    {
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name',
            ['name' => 'messenger_messages'],
        );

        return 0 < (int) (\is_scalar($count) ? $count : 0);
    }

    private function countFailedRows(Connection $connection): int
    {
        $count = $connection->fetchOne(
            "SELECT COUNT(*) FROM shared__messenger_messages WHERE queue_name = 'failed'",
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
