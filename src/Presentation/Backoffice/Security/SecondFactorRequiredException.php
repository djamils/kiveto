<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The password is right; the sign-in waits for the second factor (or for
 * its configuration). Not a refusal: the audit log does not count it.
 */
final class SecondFactorRequiredException extends AuthenticationException
{
    public function __construct(public readonly bool $enrolled)
    {
        parent::__construct('A second factor is required.');
    }

    public function getMessageKey(): string
    {
        return 'Saisis le code de ton application d’authentification.';
    }
}
