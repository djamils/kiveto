<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Exception;

/** A chosen password too short (or longer than any hasher accepts). */
final class UnacceptablePasswordException extends \RuntimeException
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 4096;

    public static function check(string $plainPassword): void
    {
        $length = mb_strlen($plainPassword);

        if ($length < self::MIN_LENGTH) {
            throw new self(\sprintf('Choisissez un mot de passe d’au moins %d caractères.', self::MIN_LENGTH));
        }

        if (\strlen($plainPassword) > self::MAX_LENGTH) {
            throw new self('Ce mot de passe est trop long.');
        }
    }
}
