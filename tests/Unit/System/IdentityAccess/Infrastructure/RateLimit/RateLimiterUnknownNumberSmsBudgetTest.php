<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Infrastructure\RateLimit\RateLimiterUnknownNumberSmsBudget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * D24 (2), S7a': the three layers of the unknown-number budget — per
 * network, per device hint, global — and one critical log per window.
 */
final class RateLimiterUnknownNumberSmsBudgetTest extends TestCase
{
    private BufferingLogger $logger;

    private RateLimiterUnknownNumberSmsBudget $budget;

    protected function setUp(): void
    {
        $this->logger = new BufferingLogger();
        $this->budget = new RateLimiterUnknownNumberSmsBudget(
            self::limiter('portal_otp_unknown_network', 2),
            self::limiter('portal_otp_unknown_device', 1),
            self::limiter('portal_otp_unknown_global', 2),
            self::limiter('portal_otp_unknown_alert', 1),
            $this->logger,
        );
    }

    public function testTheGlobalBudgetIsSpentThenRefusedWithASingleCriticalLog(): void
    {
        self::assertSame([true, true], [$this->budget->consumeGlobal(), $this->budget->consumeGlobal()]);
        self::assertSame([], $this->logs());

        self::assertFalse($this->budget->consumeGlobal());
        self::assertFalse($this->budget->consumeGlobal());
        self::assertSame([['critical', 'Portal: hourly SMS budget for unknown numbers exhausted, sign-in codes are no longer sent to them.']], $this->logs(), 'one log per window');
    }

    public function testEachNetworkAndEachDeviceHasItsOwnBudget(): void
    {
        self::assertSame([true, true, false], [$this->budget->consumeNetwork('203.0.113.0/24'), $this->budget->consumeNetwork('203.0.113.0/24'), $this->budget->consumeNetwork('203.0.113.0/24')]);
        self::assertTrue($this->budget->consumeNetwork('198.51.100.0/24'));

        self::assertSame([true, false], [$this->budget->consumeDevice('device-a'), $this->budget->consumeDevice('device-a')]);
        self::assertTrue($this->budget->consumeDevice('device-b'));
        self::assertSame([], $this->logs(), 'only the global cap is logged');
    }

    /**
     * @return list<array{mixed, mixed}> level and message of each record
     */
    private function logs(): array
    {
        $logs = [];
        foreach ($this->logger->cleanLogs() as $record) {
            \assert(\is_array($record));
            $logs[] = [$record[0] ?? null, $record[1] ?? null];
        }

        return $logs;
    }

    private static function limiter(string $id, int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'], new InMemoryStorage());
    }
}
