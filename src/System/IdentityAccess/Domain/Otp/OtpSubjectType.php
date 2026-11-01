<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/** What a challenge is bound to; fixed by its purpose. */
enum OtpSubjectType: string
{
    case INVITATION = 'invitation';
    case USER       = 'user';
}
