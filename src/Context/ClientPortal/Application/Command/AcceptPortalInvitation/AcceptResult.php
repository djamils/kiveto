<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitation;

/**
 * Outcome of an acceptance. `INVITATION_EXPIRED` and
 * `INVITATION_ALREADY_USED` only come from the code route (the invitation is
 * found by its token, as on the lookup); the grant route answers
 * `ONBOARDING_INVALID` or `INVITATION_INVALID` instead (use-time rule).
 */
enum AcceptResult: string
{
    case ACCEPTED                = 'accepted';
    case OTP_INVALID             = 'otp_invalid';
    case OTP_EXPIRED             = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED   = 'otp_attempts_exceeded';
    case ONBOARDING_INVALID      = 'onboarding_invalid';
    case INVITATION_INVALID      = 'invitation_invalid';
    case INVITATION_EXPIRED      = 'invitation_expired';
    case INVITATION_ALREADY_USED = 'invitation_already_used';
    case ACCOUNT_DISABLED        = 'account_disabled';
}
