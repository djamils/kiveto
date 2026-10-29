<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Messaging;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

/**
 * Retry policy of `async_external_notification`: a bounded number of
 * retries with a growing delay, never shorter than the provider's
 * Retry-After when it sent one (429). Bounded, unlike a recoverable
 * exception, so that a provider that keeps refusing ends in FAILED.
 */
final readonly class DispatchRetryStrategy implements RetryStrategyInterface
{
    private MultiplierRetryStrategy $growing;

    public function __construct(int $maxRetries, int $delayMilliseconds, float $multiplier)
    {
        $this->growing = new MultiplierRetryStrategy($maxRetries, $delayMilliseconds, $multiplier);
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        return $this->growing->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        return max($this->growing->getWaitingTime($message, $throwable), 1000 * (self::retryAfter($throwable) ?? 0));
    }

    private static function retryAfter(?\Throwable $throwable): ?int
    {
        $failures = $throwable instanceof HandlerFailedException ? $throwable->getWrappedExceptions() : [$throwable];

        foreach ($failures as $failure) {
            if ($failure instanceof ExternalServiceUnavailable && null !== $failure->retryAfter()) {
                return $failure->retryAfter();
            }
        }

        return null;
    }
}
