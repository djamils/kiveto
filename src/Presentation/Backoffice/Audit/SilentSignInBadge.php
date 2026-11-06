<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Audit;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * Carried by a sign-in that is not the account holder's own (a support
 * session opened by the Kiveto team): it is audited by what opened it, not
 * as the holder's sign-in.
 */
final class SilentSignInBadge implements BadgeInterface
{
    public function isResolved(): bool
    {
        return true;
    }
}
