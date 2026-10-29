<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/** Codes go to the mobile phone only; e-mail joins once addresses are verified. */
enum OtpChannel: string
{
    case SMS = 'sms';
}
