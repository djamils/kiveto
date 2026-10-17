<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Lock;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class DoctrineDbalLockStoreTest extends KernelTestCase
{
    public function testSecondLockOnTheSameKeyIsRefusedUntilTheFirstIsReleased(): void
    {
        $container = self::getContainer();

        $factory = $container->get('test.shared.lock.factory');
        \assert($factory instanceof LockFactory);

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);

        $first  = $factory->createLock('test.shared-lock', autoRelease: false);
        $second = $factory->createLock('test.shared-lock', autoRelease: false);

        self::assertTrue($first->acquire());
        self::assertSame(1, $this->countKeys($connection));
        self::assertFalse($second->acquire(false));

        $first->release();
        self::assertSame(0, $this->countKeys($connection));

        self::assertTrue($second->acquire(false));
        $second->release();
    }

    private function countKeys(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM shared__lock_keys');

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
