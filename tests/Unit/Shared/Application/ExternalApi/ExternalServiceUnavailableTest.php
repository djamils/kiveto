<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\ExternalApi;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExternalServiceUnavailable::class)]
final class ExternalServiceUnavailableTest extends TestCase
{
    public function testARemoteRateLimitIsThrottledAndCarriesRetryAfter(): void
    {
        $e = ExternalServiceUnavailable::rateLimited('registry', 30);

        self::assertTrue($e->isThrottled());
        self::assertSame(30, $e->retryAfter());
        self::assertSame('registry', $e->service());
        self::assertSame('registry is rate limiting our calls (retry after 30 s).', $e->getMessage());
    }

    public function testARemoteRateLimitWithoutRetryAfter(): void
    {
        $e = ExternalServiceUnavailable::rateLimited('registry');

        self::assertTrue($e->isThrottled());
        self::assertNull($e->retryAfter());
        self::assertSame('registry is rate limiting our calls.', $e->getMessage());
    }

    public function testALocalBudgetSpentIsThrottled(): void
    {
        $e = ExternalServiceUnavailable::throttled('registry');

        self::assertTrue($e->isThrottled());
        self::assertNull($e->retryAfter());
        self::assertSame('The local budget for registry is spent.', $e->getMessage());
    }

    public function testAnUnreachableServiceIsDownAndKeepsTheCause(): void
    {
        $cause = new \RuntimeException('timeout');
        $e     = ExternalServiceUnavailable::unreachable('vies', $cause);

        self::assertFalse($e->isThrottled());
        self::assertSame($cause, $e->getPrevious());
        self::assertSame('vies could not be reached.', $e->getMessage());
    }

    public function testA503IsAnOutageNeverThrottled(): void
    {
        $e = ExternalServiceUnavailable::failed('registry', 503);

        self::assertFalse($e->isThrottled());
        self::assertSame('registry failed (HTTP 503).', $e->getMessage());
    }

    public function testAnApiLevelOutageIsDown(): void
    {
        $e = ExternalServiceUnavailable::refused('vies', 'MS_UNAVAILABLE');

        self::assertFalse($e->isThrottled());
        self::assertSame('vies is unavailable: MS_UNAVAILABLE.', $e->getMessage());
    }
}
