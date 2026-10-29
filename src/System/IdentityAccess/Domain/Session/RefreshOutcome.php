<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Session;

/**
 * What presenting a refresh token led to. Only the first two hand out a new
 * pair; every other one asks the owner to sign in again with a code.
 */
enum RefreshOutcome: string
{
    case ROTATED = 'rotated';
    /** The same token presented again within 30 s: one more pair, same family. */
    case GRACE_REISSUED = 'grace_reissued';
    /** Replayed too late, or twice: the whole family is revoked. */
    case REUSE_DETECTED = 'reuse_detected';
    /** The account is no longer an active owner account: family revoked. */
    case USER_INACTIVE = 'user_inactive';
    case EXPIRED       = 'expired';
    case REVOKED       = 'revoked';
    case UNKNOWN       = 'unknown';

    public function issuesPair(): bool
    {
        return self::ROTATED === $this || self::GRACE_REISSUED === $this;
    }
}
