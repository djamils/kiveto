<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

/**
 * High-entropy opaque secrets (links, refresh tokens, onboarding grants):
 * only their digest is ever stored, so whoever reads a table cannot use them.
 */
interface OpaqueTokenHasherInterface
{
    /** A fresh secret: 32 random bytes, base64url without padding. */
    public function newSecret(): string;

    /** The stored digest of a secret (sha256, hex). */
    public function hash(string $secret): string;
}
