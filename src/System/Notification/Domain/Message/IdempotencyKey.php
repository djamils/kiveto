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

    /** A requester's key leaves room for the suffix of its e-mail fallback. */
    public const int REQUESTED_MAX_LENGTH = self::MAX_LENGTH - 9;

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

    /**
     * A key as a requester gives it: short enough for its fallback key to
     * fit too, so that a capped SMS never fails its whole request.
     */
    public static function requested(string $value): self
    {
        $key = self::fromString($value);

        if (mb_strlen($key->value) > self::REQUESTED_MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Idempotency key "%s" longer than %d characters.', $key->value, self::REQUESTED_MAX_LENGTH));
        }

        return $key;
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
