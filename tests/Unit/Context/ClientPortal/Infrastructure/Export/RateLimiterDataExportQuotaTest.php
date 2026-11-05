<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Export;

use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;
use App\Context\ClientPortal\Infrastructure\Export\RateLimiterDataExportQuota;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RateLimiterDataExportQuotaTest extends TestCase
{
    public function testOneExportPerAccountPer24HoursGivenBackOnRefund(): void
    {
        $quota = new RateLimiterDataExportQuota(new RateLimiterFactory(['id' => 'portal_data_export', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '24 hours'], new InMemoryStorage()));

        $quota->consume('user-1');
        $quota->consume('user-2');

        try {
            $quota->consume('user-1');
            self::fail('A second export within 24 hours should have been refused.');
        } catch (DataExportRateLimitedException $e) {
            self::assertGreaterThan(86_000, $e->retryAfterSeconds);
            // The rate limiter rounds the wait up to the next second.
            self::assertLessThanOrEqual(86_401, $e->retryAfterSeconds);
        }

        $quota->refund('user-1');
        $quota->consume('user-1');
        $this->expectException(DataExportRateLimitedException::class);
        $quota->consume('user-2');
    }
}
