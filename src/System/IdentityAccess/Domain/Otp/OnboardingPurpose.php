<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/**
 * What an onboarding grant opens: the pending invitations of the phone
 * (reusable for 15 minutes, across invitations), or the creation of an
 * owner account without invitation (single use). Neither route accepts the
 * other's grant.
 */
enum OnboardingPurpose: string
{
    case INVITATION   = 'invitation';
    case REGISTRATION = 'registration';
}
