<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces;

use App\Shared\Application\Bus\CommandInterface;

/**
 * First step of the erasure of a disabled owner account: takes the per-phone
 * lock (held until the caller's transaction ends), then deletes the
 * one-time code challenges of the phone and of the user (onboarding grants
 * included), the phone's quota counters and the user's idempotency keys.
 * Answers the phone and e-mail the account still has (both null once
 * anonymised: a retry finds nothing left to purge). Idempotent.
 */
final readonly class PurgePortalPhoneTraces implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
