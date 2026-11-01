<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\CompletePortalRegistration;

enum RegistrationResult: string
{
    /** Account and profile created now. */
    case REGISTERED = 'registered';
    /** A retry with the same grant: the account it created, nothing written. */
    case REPLAYED           = 'replayed';
    case ONBOARDING_INVALID = 'onboarding_invalid';
    case ACCOUNT_DISABLED   = 'account_disabled';
}
