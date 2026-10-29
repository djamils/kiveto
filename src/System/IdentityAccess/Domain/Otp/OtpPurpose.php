<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/**
 * A code proves a phone for one purpose: a login code never accepts an
 * invitation, an invitation code never opens a session.
 */
enum OtpPurpose: string
{
    case LOGIN      = 'login';
    case INVITATION = 'invitation';
}
