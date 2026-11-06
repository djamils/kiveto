<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * A wrong app or backup code; after five, the sign-in starts over.
 */
final class InvalidSecondFactorException extends AuthenticationException
{
    public function __construct(public readonly bool $restart)
    {
        parent::__construct('Invalid second factor code.');
    }

    public function getMessageKey(): string
    {
        return $this->restart
            ? 'Trop de codes invalides. Reconnecte-toi.'
            : 'Ce code n’est pas valide. Vérifie l’heure de ton téléphone et réessaie.';
    }
}
