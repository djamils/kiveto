<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use PHPUnit\Framework\TestCase;

final class TotpSecretTest extends TestCase
{
    public function testTheKeyIsHandedOverInBase32(): void
    {
        $secret = TotpSecret::fromBytes('12345678901234567890');

        self::assertSame('12345678901234567890', $secret->bytes());
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret->toBase32());
        self::assertSame('12345678901234567890', TotpSecret::fromBase32('gezd gnbv gy3t qojq gezd gnbv gy3t qojq')->bytes());
    }

    public function testComparesTwoKeysByTheirBytes(): void
    {
        $secret = TotpSecret::fromBytes('12345678901234567890');

        self::assertTrue($secret->equals(TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')));
        self::assertFalse($secret->equals(TotpSecret::fromBytes('12345678901234567891')));
    }

    public function testEightyBitsAreTheLeastAKeyHolds(): void
    {
        self::assertSame('0123456789', TotpSecret::fromBytes('0123456789')->bytes());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A TOTP secret needs at least 80 bits.');

        TotpSecret::fromBytes('012345678');
    }

    public function testAnEmptyBase32KeyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TotpSecret::fromBase32('');
    }
}
