<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange;

enum EmailChangeResult: string
{
    case CONFIRMED             = 'confirmed';
    case OTP_INVALID           = 'otp_invalid';
    case OTP_EXPIRED           = 'otp_expired';
    case OTP_ATTEMPTS_EXCEEDED = 'otp_attempts_exceeded';
    /** Another owner account verified this address first; the code is used. */
    case EMAIL_TAKEN = 'email_taken';
}
