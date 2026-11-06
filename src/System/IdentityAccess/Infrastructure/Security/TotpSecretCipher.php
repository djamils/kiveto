<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the authenticator keys at rest (libsodium secretbox, a key
 * derived from the kernel secret): a copy of the table alone gives no code.
 * Rotating APP_SECRET makes the stored keys unreadable — every admin then
 * configures his app again.
 */
final readonly class TotpSecretCipher
{
    private const string CONTEXT = 'identity_access.totp_secret.v1';

    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $kernelSecret)
    {
        // HMAC-SHA256 gives the 32 bytes secretbox needs, whatever the secret's length.
        $this->key = hash_hmac('sha256', self::CONTEXT, $kernelSecret, true);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $stored): string
    {
        $raw = base64_decode($stored, true);

        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Unreadable second factor key.');
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        if (false === $plain) {
            throw new \RuntimeException('Unreadable second factor key (another kernel secret?).');
        }

        return $plain;
    }
}
