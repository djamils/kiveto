<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use PHPUnit\Framework\TestCase;

final class OpaqueTokenHasherTest extends TestCase
{
    public function testASecretIsUrlSafeAndUnique(): void
    {
        $hasher = new OpaqueTokenHasher();
        $secret = $hasher->newSecret();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $secret, '32 bytes, base64url, no padding');
        self::assertNotSame($secret, $hasher->newSecret());
    }

    public function testTheHashIsASha256HexDigest(): void
    {
        $hasher = new OpaqueTokenHasher();

        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $hasher->hash('abc'));
        self::assertSame($hasher->hash('abc'), $hasher->hash('abc'));
    }
}
