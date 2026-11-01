<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

/**
 * A code proves a phone (or, for `email_verification`, an e-mail address)
 * for one purpose: a login code never accepts an invitation, an invitation
 * code never opens a session, an action code confirms one action of one
 * user.
 */
enum OtpPurpose: string
{
    case LOGIN              = 'login';
    case INVITATION         = 'invitation';
    case EMAIL_CHANGE       = 'email_change';
    case ACCOUNT_DELETION   = 'account_deletion';
    case EMAIL_VERIFICATION = 'email_verification';

    /**
     * What the challenge is bound to: the invitation of an invitation code,
     * the user of an action code, nothing for a login code.
     */
    public function subjectType(): ?OtpSubjectType
    {
        return match ($this) {
            self::LOGIN      => null,
            self::INVITATION => OtpSubjectType::INVITATION,
            default          => OtpSubjectType::USER,
        };
    }

    /**
     * An SMS code confirming an action of a signed-in owner, sent to the
     * phone of their account.
     */
    public function isAction(): bool
    {
        return self::EMAIL_CHANGE === $this || self::ACCOUNT_DELETION === $this;
    }
}
