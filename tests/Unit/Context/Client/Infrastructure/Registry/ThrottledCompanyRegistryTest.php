<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Infrastructure\Registry\CachedCompanyRegistry;
use App\Context\Client\Infrastructure\Registry\ThrottledCompanyRegistry;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Tests\Unit\Context\Client\Infrastructure\Registry\Fixture\CountingRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Real token buckets and real time: the bucket refills by itself within a second.
 */
#[CoversClass(ThrottledCompanyRegistry::class)]
final class ThrottledCompanyRegistryTest extends TestCase
{
    public function testTheFifthSearchInTheSameSecondIsThrottledWithoutCall(): void
    {
        $inner    = new CountingRegistry();
        $registry = new ThrottledCompanyRegistry($inner, self::bucket(4), self::bucket(1));

        foreach (['a1', 'a2', 'a3', 'a4'] as $query) {
            $registry->search('calanques ' . $query, 5);
        }

        try {
            $registry->search('calanques', 5);
            self::fail('The fifth search was expected to be throttled.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertTrue($e->isThrottled());
            self::assertSame('recherche-entreprises', $e->service());
        }

        self::assertSame(4, $inner->searches);
    }

    public function testASirenLookupWaitsForATokenInsteadOfFailing(): void
    {
        $inner    = new CountingRegistry();
        $registry = new ThrottledCompanyRegistry($inner, self::bucket(4), self::bucket(1));

        $start = microtime(true);
        $registry->findBySiren('523456788');
        $record = $registry->findBySiren('812345676');

        self::assertNotNull($record);
        self::assertSame(2, $inner->lookups);
        self::assertGreaterThanOrEqual(0.9, microtime(true) - $start);
    }

    public function testACacheHitSpendsNoToken(): void
    {
        $inner    = new CountingRegistry();
        $registry = new CachedCompanyRegistry(new ThrottledCompanyRegistry($inner, self::bucket(2), self::bucket(1)), new ArrayAdapter());

        $registry->search('calanques', 5);
        $registry->search('calanques', 5);
        $registry->search('mont', 5);

        self::assertSame(2, $inner->searches);
    }

    private static function bucket(int $perSecond): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'test_' . $perSecond, 'policy' => 'token_bucket', 'limit' => $perSecond, 'rate' => ['interval' => '1 second', 'amount' => $perSecond]],
            new InMemoryStorage(),
        );
    }
}
