<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\RateLimit;

use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/**
 * Companion to {@see StubRateLimiterFactory}. Routes consume() calls back into
 * the parent factory so call recording stays centralised.
 */
final class StubLimiter implements LimiterInterface
{
    public function __construct(
        private readonly string $key,
        private readonly StubRateLimiterFactory $factory,
    ) {
    }

    public function consume(int $tokens = 1): RateLimit
    {
        return $this->factory->recordConsume($this->key, $tokens);
    }

    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(self::class);
    }

    public function reset(): void
    {
        // no-op for the stub
    }
}
