<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Infrastructure\Registry\CachedCompanyRegistry;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Tests\Unit\Context\Client\Infrastructure\Registry\Fixture\CountingRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CachedCompanyRegistry::class)]
final class CachedCompanyRegistryTest extends TestCase
{
    public function testASearchRepeatedWithinTheHourIsServedFromTheCache(): void
    {
        $inner    = new CountingRegistry();
        $registry = new CachedCompanyRegistry($inner, new ArrayAdapter());

        $first  = $registry->search('Calanques', 5);
        $second = $registry->search(' calanques ', 5);

        self::assertSame(1, $inner->searches);
        self::assertSame('523456788', $second[0]->siren);
        self::assertCount(\count($first), $second);
    }

    public function testADifferentLimitIsADifferentSearch(): void
    {
        $inner    = new CountingRegistry();
        $registry = new CachedCompanyRegistry($inner, new ArrayAdapter());

        $registry->search('mont', 5);
        $registry->search('mont', 1);

        self::assertSame(2, $inner->searches);
    }

    public function testTheSearchIsKeptAnHour(): void
    {
        $cache    = new ArrayAdapter();
        $registry = new CachedCompanyRegistry(new CountingRegistry(), $cache);

        $registry->search('mont', 5);

        $expiries = new \ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($cache);
        self::assertIsArray($expiries);
        self::assertCount(1, $expiries);
        $expiry = array_values($expiries)[0];
        self::assertIsFloat($expiry);
        self::assertEqualsWithDelta(time() + 3600, $expiry, 5);
    }

    public function testASirenLookupIsNeverCached(): void
    {
        $inner    = new CountingRegistry();
        $registry = new CachedCompanyRegistry($inner, new ArrayAdapter());

        $registry->findBySiren('523456788');
        $registry->findBySiren('523456788');

        self::assertSame(2, $inner->lookups);
    }

    public function testAnOutageIsNeverCached(): void
    {
        $inner    = new CountingRegistry();
        $registry = new CachedCompanyRegistry($inner, new ArrayAdapter());

        foreach ([1, 2] as $_) {
            try {
                $registry->search(FixtureCompanyRegistry::UNAVAILABLE_QUERY, 5);
                self::fail('An outage was expected.');
            } catch (ExternalServiceUnavailable) {
            }
        }

        self::assertSame(2, $inner->searches);
    }
}
