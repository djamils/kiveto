<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Application\Port\SecondFactorSecretsInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;

final readonly class RandomSecondFactorSecrets implements SecondFactorSecretsInterface
{
    /** No 0/O, 1/I/L: a code read from paper is typed right. */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const string LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    public function newTotpSecret(): TotpSecret
    {
        return TotpSecret::fromBytes(random_bytes(20));
    }

    public function newBackupCode(): string
    {
        $code = '';
        for ($i = 0; $i < 10; ++$i) {
            $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        // One place always holds a letter: a code is never mistaken for an app code.
        $code[random_int(0, 9)] = self::LETTERS[random_int(0, \strlen(self::LETTERS) - 1)];

        return substr($code, 0, 5) . '-' . substr($code, 5);
    }
}
