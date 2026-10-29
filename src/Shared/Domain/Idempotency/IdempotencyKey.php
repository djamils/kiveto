<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

/**
 * Client-chosen key identifying one logical write (the `Idempotency-Key`
 * header): 1 to 128 visible ASCII characters.
 */
final class IdempotencyKey
{
    public const int MAX_LENGTH = 128;

    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @throws InvalidIdempotencyKeyException
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,' . self::MAX_LENGTH . '}\z/', $value)) {
            throw new InvalidIdempotencyKeyException();
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
