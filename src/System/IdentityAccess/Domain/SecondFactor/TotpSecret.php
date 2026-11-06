<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

/**
 * The shared key of an authenticator app: 20 random bytes (160 bits, the
 * size RFC 4226 recommends), handed over in base32.
 */
final class TotpSecret
{
    private const int MIN_BYTES = 10;

    private string $bytes;

    private function __construct()
    {
    }

    public static function fromBytes(string $bytes): self
    {
        if (\strlen($bytes) < self::MIN_BYTES) {
            throw new \InvalidArgumentException('A TOTP secret needs at least 80 bits.');
        }

        $secret        = new self();
        $secret->bytes = $bytes;

        return $secret;
    }

    public static function fromBase32(string $text): self
    {
        return self::fromBytes(Base32::decode($text));
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function toBase32(): string
    {
        return Base32::encode($this->bytes);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }
}
