<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

enum LoginOtpResult: string
{
    /** The code is right and the phone belongs to an active owner account. */
    case AUTHENTICATED = 'authenticated';
    /**
     * The code is right, the phone has no account but pending invitations:
     * an onboarding grant accepts them.
     */
    case INVITATION_PENDING = 'invitation_pending';
    /**
     * The code is right but the phone has no account nor invitation: a
     * registration grant (`onboardingToken`) creates the account once.
     */
    case REGISTRATION_REQUIRED = 'registration_required';
    /** The code is right but the owner account is disabled: no session. */
    case ACCOUNT_DISABLED = 'account_disabled';
    /** Wrong code, unknown challenge, or a challenge already used. */
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
}
