<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\RateLimit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * AC 9: two containers (two connections outside the test transaction, as
 * in production) share the `portal_otp_phone` quota through the database
 * cache and the database lock: neither consumption is lost, the one over
 * the limit is refused.
 */
final class PortalOtpLimiterConcurrencyTest extends KernelTestCase
{
    private const string PHONE = '+33612340901';
    private const string STATE = 'portal_otp_phone-' . self::PHONE;

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());

        $this->storage($this->first)->delete(self::STATE);
    }

    protected function tearDown(): void
    {
        $this->storage($this->first)->delete(self::STATE);
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTwoContainersCountEveryConsumptionOfTheSameKey(): void
    {
        $first  = $this->factory($this->first);
        $second = $this->factory($this->second);

        self::assertTrue($first->create(self::PHONE)->consume()->isAccepted());
        self::assertTrue($second->create(self::PHONE)->consume()->isAccepted());

        // At the threshold: one request on each container.
        $last = $first->create(self::PHONE)->consume();
        $over = $second->create(self::PHONE)->consume();

        self::assertSame([true, 0], [$last->isAccepted(), $last->getRemainingTokens()]);
        self::assertSame([false, 0], [$over->isAccepted(), $over->getRemainingTokens()]);
        self::assertFalse($first->create(self::PHONE)->consume()->isAccepted(), 'the refusal is seen by the other container too');
        $rows = $this->first->fetchOne('SELECT COUNT(*) FROM shared__rate_limiter_cache WHERE item_id = ?', ['portal:' . sha1(self::STATE)]);
        \assert(is_numeric($rows));
        self::assertSame(1, (int) $rows, 'one shared row');
    }

    private function factory(Connection $connection): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'portal_otp_phone', 'policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
            $this->storage($connection),
            new LockFactory(new DoctrineDbalStore($connection, ['db_table' => 'shared__lock_keys'])),
        );
    }

    private function storage(Connection $connection): CacheStorage
    {
        return new CacheStorage(new DoctrineDbalAdapter($connection, 'portal', 0, ['db_table' => 'shared__rate_limiter_cache']));
    }
}
