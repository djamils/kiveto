<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * The opaque key the requester builds for a message (formats documented by
 * each requester); one message per key, so a replayed request never sends twice.
 */
final readonly class IdempotencyKey
{
    public const int MAX_LENGTH = 190;

    private const string FALLBACK_SUFFIX = ':fallback';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = mb_trim($value);

        if ('' === $value || mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Invalid idempotency key "%s".', $value));
        }

        return new self($value);
    }

    /** The key of the e-mail sent instead of a capped SMS. */
    public function fallback(): self
    {
        return self::fromString($this->value . self::FALLBACK_SUFFIX);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
