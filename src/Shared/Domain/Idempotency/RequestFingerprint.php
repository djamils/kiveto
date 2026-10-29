<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

/**
 * SHA-256 (hex) of the canonical form of a request. Two requests sent with the
 * same idempotency key must carry the same fingerprint to be replayed.
 */
final class RequestFingerprint
{
    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $canonicalRequest): self
    {
        return new self(hash('sha256', $canonicalRequest));
    }

    public static function fromHash(string $hash): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}\z/', $hash)) {
            throw new \InvalidArgumentException('A request fingerprint must be a lowercase SHA-256 hex digest.');
        }

        return new self($hash);
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
