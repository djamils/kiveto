<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;

/**
 * Guards the implicit outbox: every Doctrine transport must write to the messenger table,
 * on the business connection, without creating tables at runtime (DDL would implicitly
 * commit an open MySQL transaction).
 */
final class DoctrineTransportsConfigurationTest extends KernelTestCase
{
    public function testEveryDoctrineTransportUsesTheBusinessConnectionWithoutAutoSetup(): void
    {
        $container = self::getContainer();

        $receivers = $container->get('messenger.receiver_locator');
        \assert($receivers instanceof ServiceLocator);

        $businessConnection = $container->get('doctrine.dbal.default_connection');
        \assert($businessConnection instanceof DbalConnection);

        $checked = [];
        $queues  = [];

        foreach (array_keys($receivers->getProvidedServices()) as $name) {
            if (str_starts_with($name, 'messenger.transport.')) {
                continue;
            }

            $transport = $receivers->get($name);

            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $connection = $this->readProperty($transport, DoctrineTransport::class, 'connection');
            \assert($connection instanceof Connection);

            $configuration = $connection->getConfiguration();

            self::assertFalse($configuration['auto_setup'], \sprintf('Transport "%s" must not auto-setup.', $name));
            self::assertSame('shared__messenger_messages', $configuration['table_name'], \sprintf('Transport "%s" table.', $name));
            self::assertSame(
                $businessConnection,
                $this->readProperty($connection, Connection::class, 'driverConnection'),
                \sprintf('Transport "%s" must use the business connection.', $name),
            );

            $checked[]     = $name;
            $queues[$name] = $configuration['queue_name'];
        }

        sort($checked);
        self::assertSame(['async', 'async_external', 'async_external_batch', 'async_external_notification', 'failed'], $checked);
        self::assertSame('external', $queues['async_external']);
        self::assertSame('external_batch', $queues['async_external_batch']);
        self::assertSame('external_notification', $queues['async_external_notification']);
        self::assertSame('failed', $queues['failed']);
    }

    /**
     * @param class-string $declaringClass
     */
    private function readProperty(object $object, string $declaringClass, string $property): mixed
    {
        return new \ReflectionProperty($declaringClass, $property)->getValue($object);
    }
}
