<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

enum IdentityClaimResult: string
{
    /** Proof verified now. */
    case VERIFIED = 'verified';
    /** The same invitation code presented again before its original expiry (retry). */
    case ALREADY_VERIFIED      = 'already_verified';
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
    /** Unknown, expired or revoked onboarding grant. */
    case ONBOARDING_INVALID = 'onboarding_invalid';
    /** The grant proves another phone than the invitation's. */
    case PHONE_MISMATCH = 'phone_mismatch';
    /** The phone's owner account is disabled: nothing claimed. */
    case ACCOUNT_DISABLED = 'account_disabled';
}
