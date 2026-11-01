<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/**
 * How the last code of a challenge was sent: by SMS, or by e-mail (an
 * address being verified, or a login code resent to a verified address).
 */
enum OtpChannel: string
{
    case SMS   = 'sms';
    case EMAIL = 'email';
}
