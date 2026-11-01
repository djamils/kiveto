<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The outcome of the SMS code confirming an account deletion. A code of
 * another user or purpose (a sign-in code), an unknown challenge and a code
 * already used are all `OTP_INVALID`.
 */
enum DeletionCodeCheck: string
{
    case VERIFIED              = 'verified';
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
}
