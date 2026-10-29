<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/**
 * A verification never throws: a wrong code must persist its attempt, so it
 * is a result the handler commits, not an exception that rolls it back.
 */
enum OtpVerificationResult: string
{
    case VERIFIED = 'verified';
    /**
     * The challenge was already consumed for this same purpose, and the right
     * code is presented again before its original expiry: a retry after a
     * lost response. Only the acceptance commands treat it as a success.
     */
    case ALREADY_VERIFIED  = 'already_verified';
    case INVALID           = 'invalid';
    case EXPIRED           = 'expired';
    case ATTEMPTS_EXCEEDED = 'attempts_exceeded';
}
