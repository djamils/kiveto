<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The owner account (IdentityAccess) through its deletion: the SMS code, the
 * immediate switch-off, then the first and last steps of the erasure.
 * Every call runs in the caller's transaction.
 */
interface PortalAccountErasureInterface
{
    /** Checks the `account_deletion` code of the user; a wrong attempt is counted. */
    public function verifyDeletionCode(string $userId, string $challengeId, string $code): DeletionCodeCheck;

    /** Disables the account and revokes every refresh token. */
    public function disable(string $userId): void;

    /**
     * Takes the per-phone lock (until the transaction ends), then purges the
     * phone's challenges, onboarding grants, quota counters and the user's
     * idempotency keys.
     */
    public function purgeTraces(string $userId): ErasedOwner;

    /** Erases phone and e-mail of the account, then deletes the phone's lock row. */
    public function anonymise(string $userId): void;
}
