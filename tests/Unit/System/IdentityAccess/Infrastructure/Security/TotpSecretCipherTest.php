<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Infrastructure\Security\TotpSecretCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpSecretCipherTest extends TestCase
{
    private const string KEY = '12345678901234567890';

    /**
     * Whatever the length of the kernel secret, it gives the 32-byte key the
     * cipher needs.
     */
    #[DataProvider('provideAKeyIsReadBackWithTheSameKernelSecretCases')]
    public function testAKeyIsReadBackWithTheSameKernelSecret(string $kernelSecret): void
    {
        $stored = new TotpSecretCipher($kernelSecret)->encrypt(self::KEY);

        self::assertSame(self::KEY, new TotpSecretCipher($kernelSecret)->decrypt($stored));
        self::assertStringNotContainsString(self::KEY, (string) base64_decode($stored, true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAKeyIsReadBackWithTheSameKernelSecretCases(): iterable
    {
        yield 'one character' => ['x'];

        yield 'usual 32 hex characters' => ['0123456789abcdef0123456789abcdef'];

        yield 'very long' => [str_repeat('kernel-secret', 50)];
    }

    public function testEachEncryptionDrawsItsOwnNonce(): void
    {
        $cipher = new TotpSecretCipher('kernel-secret');

        $first  = $cipher->encrypt(self::KEY);
        $second = $cipher->encrypt(self::KEY);

        self::assertNotSame($first, $second);
        self::assertSame([self::KEY, self::KEY], [$cipher->decrypt($first), $cipher->decrypt($second)]);
    }

    public function testAnotherKernelSecretCannotReadTheKey(): void
    {
        $stored = new TotpSecretCipher('kernel-secret')->encrypt(self::KEY);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unreadable second factor key (another kernel secret?).');

        new TotpSecretCipher('another-kernel-secret')->decrypt($stored);
    }

    public function testATamperedKeyIsRefused(): void
    {
        $cipher  = new TotpSecretCipher('kernel-secret');
        $raw     = (string) base64_decode($cipher->encrypt(self::KEY), true);
        $raw[30] = \chr(\ord($raw[30]) ^ 0x01);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unreadable second factor key (another kernel secret?).');

        $cipher->decrypt(base64_encode($raw));
    }

    #[DataProvider('provideAnUnreadableValueIsRefusedCases')]
    public function testAnUnreadableValueIsRefused(string $stored): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unreadable second factor key.');

        new TotpSecretCipher('kernel-secret')->decrypt($stored);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnUnreadableValueIsRefusedCases(): iterable
    {
        yield 'not base64' => ['%%% not base64 %%%'];

        yield 'empty' => [''];

        yield 'only a nonce' => [base64_encode(str_repeat("\0", \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES))];
    }
}
