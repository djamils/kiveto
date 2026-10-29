<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\RateLimit;

use App\System\IdentityAccess\Infrastructure\RateLimit\RateLimiterUnknownNumberSmsBudget;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * D24 (2): the global budget of unknown numbers, and one alert per window.
 */
final class RateLimiterUnknownNumberSmsBudgetTest extends TestCase
{
    public function testTheBudgetIsSpentThenRefusedWithASingleAlert(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                \assert(\is_string($level));
                $this->records[] = [$level, (string) $message];
            }
        };
        $budget = new RateLimiterUnknownNumberSmsBudget(
            new RateLimiterFactory(['id' => 'portal_otp_unknown_global', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 hour'], new InMemoryStorage()),
            new RateLimiterFactory(['id' => 'portal_otp_unknown_alert', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], new InMemoryStorage()),
            $logger,
        );

        self::assertSame([true, true], [$budget->consume(), $budget->consume()]);
        self::assertSame([], $logger->records);

        self::assertFalse($budget->consume());
        self::assertSame([['alert', 'Portal: hourly SMS budget for unknown numbers exhausted, sign-in codes are no longer sent to them.']], $logger->records);

        self::assertFalse($budget->consume());
        self::assertCount(1, $logger->records, 'one alert per window');
    }
}
