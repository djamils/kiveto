<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Infrastructure\Security\RandomOtpCodeGenerator;
use PHPUnit\Framework\TestCase;

final class RandomOtpCodeGeneratorTest extends TestCase
{
    public function testACodeIsAlwaysSixDigits(): void
    {
        $generator = new RandomOtpCodeGenerator();
        $codes     = [];

        for ($i = 0; $i < 200; ++$i) {
            $code = $generator->generate();
            self::assertMatchesRegularExpression('/^\d{6}$/', $code);
            $codes[$code] = true;
        }

        self::assertGreaterThan(150, \count($codes), 'codes are random');
    }
}
