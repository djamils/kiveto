<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\RateLimit;

use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Deterministic stub for Symfony's rate limiter factory used in tests.
 *
 * Pre-program a queue of accept/reject decisions and inspect the per-key call log.
 * Zero wall-clock dependency — perfect for asserting controller wiring without
 * exercising Symfony's fixed-window algorithm itself.
 */
final class StubRateLimiterFactory implements RateLimiterFactoryInterface
{
    /**
     * Per-key queue of decisions: each entry is [accepted: bool, retryAfterSeconds: int].
     *
     * @var array<string, list<array{0: bool, 1: int}>>
     */
    private array $queues = [];

    /**
     * Per-key call log: list of consume() amounts.
     *
     * @var array<string, list<int>>
     */
    private array $callLog = [];

    /**
     * @param list<array{0: bool, 1: int}> $decisions
     */
    public function programKey(string $key, array $decisions): void
    {
        $this->queues[$key] = $decisions;
    }

    /**
     * @return list<int>
     */
    public function getCallLog(string $key): array
    {
        return $this->callLog[$key] ?? [];
    }

    /**
     * @return array<string, list<int>>
     */
    public function getAllCallLogs(): array
    {
        return $this->callLog;
    }

    public function create(?string $key = null): LimiterInterface
    {
        $bucketKey = $key ?? '__null__';

        return new StubLimiter(
            key: $bucketKey,
            factory: $this,
        );
    }

    /**
     * @internal called by StubLimiter
     */
    public function recordConsume(string $key, int $tokens): RateLimit
    {
        $this->callLog[$key][] = $tokens;

        if (!isset($this->queues[$key]) || [] === $this->queues[$key]) {
            // Default to "accepted" forever after the queue is drained.
            return new RateLimit(0, new \DateTimeImmutable('+1 second'), true, 60);
        }

        $decision      = array_shift($this->queues[$key]);
        $isAccepted    = $decision[0];
        $retryAfterSec = $decision[1];

        return new RateLimit(
            availableTokens: $isAccepted ? 1 : 0,
            retryAfter: new \DateTimeImmutable('+' . $retryAfterSec . ' seconds'),
            accepted: $isAccepted,
            limit: 60,
        );
    }
}
