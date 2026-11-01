<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterPortalUser;

enum RegistrationResult: string
{
    /** The account was created now; the grant is consumed. */
    case REGISTERED = 'registered';
    /**
     * The grant had already created the phone's account and is presented
     * again before its expiry (a retry after a lost answer): same account.
     */
    case REPLAYED = 'replayed';
    /** Unknown, expired or revoked registration grant, or another grant. */
    case ONBOARDING_INVALID = 'onboarding_invalid';
    /** The phone's owner account exists but is disabled: nothing is created. */
    case ACCOUNT_DISABLED = 'account_disabled';
}
