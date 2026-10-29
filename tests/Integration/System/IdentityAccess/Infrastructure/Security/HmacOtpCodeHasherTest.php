<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AC 61: a one-time code is stored as an HMAC keyed with the server secret,
 * never as a plain (reversible) sha256.
 */
final class HmacOtpCodeHasherTest extends KernelTestCase
{
    public function testTheCodeDigestIsKeyedWithTheKernelSecret(): void
    {
        $hasher = self::getContainer()->get(OtpCodeHasherInterface::class);
        \assert($hasher instanceof OtpCodeHasherInterface);
        $secret = self::getContainer()->getParameter('kernel.secret');
        \assert(\is_string($secret) && '' !== $secret);

        $digest = $hasher->hash('042137');

        self::assertSame(hash_hmac('sha256', '042137', $secret), $digest);
        self::assertNotSame(hash('sha256', '042137'), $digest);
        self::assertNotSame($digest, $hasher->hash('042138'));
    }
}
