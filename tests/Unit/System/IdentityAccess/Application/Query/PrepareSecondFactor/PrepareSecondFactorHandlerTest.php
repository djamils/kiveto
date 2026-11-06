<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\PrepareSecondFactor;

use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\PrepareSecondFactor;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\PrepareSecondFactorHandler;
use App\System\IdentityAccess\Application\Query\PrepareSecondFactor\SecondFactorEnrollment;
use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\TestCase;

final class PrepareSecondFactorHandlerTest extends TestCase
{
    public function testAFreshKeyIsLabelledWithTheAccountsAddress(): void
    {
        $enrollment = new PrepareSecondFactorHandler(new SecondFactorFakes())(new PrepareSecondFactor('admin@kiveto.fr'));

        self::assertSame(['GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'admin@kiveto.fr'], [$enrollment->secret, $enrollment->email]);
    }

    public function testTheQrCodeCarriesAKeyUri(): void
    {
        $enrollment = new SecondFactorEnrollment('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'djamil+console@kiveto.fr');

        self::assertSame(
            'otpauth://totp/Kiveto%20Console:djamil%2Bconsole%40kiveto.fr?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Kiveto%20Console&algorithm=SHA1&digits=6&period=30',
            $enrollment->uri(),
        );
    }

    public function testTheKeyIsGroupedByFourForTypingItByHand(): void
    {
        self::assertSame('GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ', new SecondFactorEnrollment('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'admin@kiveto.fr')->groupedSecret());
        self::assertSame('ABCD EF', new SecondFactorEnrollment('ABCDEF', 'admin@kiveto.fr')->groupedSecret());
    }
}
