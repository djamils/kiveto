<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp;

/**
 * The outcome of an action code. A code of another user or purpose, an
 * unknown challenge and a code already used are all `OTP_INVALID`.
 */
enum ActionOtpResult: string
{
    case VERIFIED              = 'verified';
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
}
