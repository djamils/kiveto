<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Infrastructure\Security\RandomSecondFactorSecrets;
use PHPUnit\Framework\TestCase;

final class RandomSecondFactorSecretsTest extends TestCase
{
    public function testAKeyHolds160RandomBits(): void
    {
        $secrets = new RandomSecondFactorSecrets();

        $first  = $secrets->newTotpSecret();
        $second = $secrets->newTotpSecret();

        self::assertSame(20, \strlen($first->bytes()));
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $first->toBase32());
        self::assertFalse($first->equals($second), 'keys are random');
    }

    /**
     * Ten characters without 0/O nor 1/I/L, grouped by five, with at least
     * one letter: never mistaken for a code of the app.
     */
    public function testABackupCodeIsReadableFromPaper(): void
    {
        $secrets = new RandomSecondFactorSecrets();
        $codes   = [];

        for ($i = 0; $i < 300; ++$i) {
            $code = $secrets->newBackupCode();
            self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{5}-[A-HJKMNP-Z2-9]{5}$/', $code);
            self::assertMatchesRegularExpression('/[A-Z]/', $code);
            $codes[$code] = true;
        }

        self::assertCount(300, $codes, 'codes are random');
    }
}
