<?php

declare(strict_types=1);

namespace App\Shared\Application\ExternalApi;

/**
 * An external service could not answer: an outage, never an empty result.
 *
 * Two families are told apart by isThrottled(): "throttled" (our local budget
 * is spent, or the service answered 429) means "try again in a moment";
 * everything else (5xx, timeout, transport error, API-level outage code) means
 * the service is down.
 */
final class ExternalServiceUnavailable extends \RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $service,
        private readonly bool $throttled,
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The remote service answered 429.
     */
    public static function rateLimited(string $service, ?int $retryAfter = null): self
    {
        return new self(
            \sprintf('%s is rate limiting our calls%s.', $service, null === $retryAfter ? '' : \sprintf(' (retry after %d s)', $retryAfter)),
            $service,
            true,
            $retryAfter,
        );
    }

    /**
     * Transport error or timeout: no answer came back.
     */
    public static function unreachable(string $service, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('%s could not be reached.', $service), $service, false, null, $previous);
    }

    /**
     * The service answered 503 or any other 5xx.
     */
    public static function failed(string $service, int $status): self
    {
        return new self(\sprintf('%s failed (HTTP %d).', $service, $status), $service, false);
    }

    /**
     * The service answered but reported an outage of its own (e.g. VIES MS_UNAVAILABLE).
     */
    public static function refused(string $service, string $reason): self
    {
        return new self(\sprintf('%s is unavailable: %s.', $service, $reason), $service, false);
    }

    /**
     * Our local budget for this service is spent: no call was made.
     */
    public static function throttled(string $service): self
    {
        return new self(\sprintf('The local budget for %s is spent.', $service), $service, true);
    }

    public function service(): string
    {
        return $this->service;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isThrottled(): bool
    {
        return $this->throttled;
    }
}
