<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimPortalIdentity;

enum ClaimResult: string
{
    /** Proof verified now; `userId` is the owner account (found or created). */
    case VERIFIED = 'verified';
    /**
     * The invitation code was already used for this invitation and is
     * presented again before its original expiry: a retry. `userId` as above.
     */
    case ALREADY_VERIFIED      = 'already_verified';
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
    /** Unknown, expired or revoked onboarding grant. */
    case ONBOARDING_INVALID = 'onboarding_invalid';
    /** The grant proves another phone than the invitation's. */
    case PHONE_MISMATCH = 'phone_mismatch';
    /** The phone's owner account exists but is disabled: nothing is claimed. */
    case ACCOUNT_DISABLED = 'account_disabled';

    public function isProven(): bool
    {
        return self::VERIFIED === $this || self::ALREADY_VERIFIED === $this;
    }
}
