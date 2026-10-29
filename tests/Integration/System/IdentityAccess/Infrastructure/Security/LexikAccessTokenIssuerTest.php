<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * D22: the access token of a Portal session, signed by lexik.
 */
final class LexikAccessTokenIssuerTest extends KernelTestCase
{
    private const string OWNER = '01960000-0000-7000-8000-0000000007e1';

    public function testTheTokenNamesTheOwnerAndLivesFifteenMinutes(): void
    {
        $issuer = self::getContainer()->get(AccessTokenIssuerInterface::class);
        \assert($issuer instanceof AccessTokenIssuerInterface);
        $encoder = self::getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $before = time();
        $issued = $issuer->issue(self::OWNER);
        $claims = $encoder->decode($issued->token);

        self::assertSame([self::OWNER, 'portal'], [$claims['sub'] ?? null, $claims['typ'] ?? null]);
        $exp = $claims['exp'] ?? null;
        self::assertIsInt($exp);
        self::assertEqualsWithDelta($before + 900, $exp, 2);
        self::assertEqualsWithDelta($exp, $issued->expiresAt->getTimestamp(), 2, 'the announced expiry is the one signed');
    }
}
