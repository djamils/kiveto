<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;

/**
 * A plain sha256 is enough here: the secrets carry 256 random bits, so their
 * digest cannot be reversed by brute force (unlike a 6-digit code).
 */
final readonly class OpaqueTokenHasher implements OpaqueTokenHasherInterface
{
    public function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
