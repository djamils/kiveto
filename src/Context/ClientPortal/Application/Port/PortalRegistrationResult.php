<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

enum PortalRegistrationResult: string
{
    /** The owner account was created now. */
    case REGISTERED = 'registered';
    /** The grant already created this account and is presented again while it lives. */
    case REPLAYED = 'replayed';
    /** Unknown, expired or already consumed registration grant (or another grant). */
    case ONBOARDING_INVALID = 'onboarding_invalid';
    /** The phone's owner account exists but is disabled: nothing is created. */
    case ACCOUNT_DISABLED = 'account_disabled';
}
