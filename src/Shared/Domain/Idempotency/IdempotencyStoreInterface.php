<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

/**
 * Records the response of a write per (principal, key) for RETENTION_HOURS and
 * replays it to retries.
 */
interface IdempotencyStoreInterface
{
    public const int RETENTION_HOURS = 24;

    /**
     * Runs `$operation` at most once per (principal, key) within the retention
     * window. Concurrent calls for the same (principal, key) are serialised:
     * the second waits for the first, then gets its recorded response.
     *
     * - no live record → runs `$operation`, records and returns its response;
     * - live record with the same fingerprint → returns the recorded response
     *   without running `$operation`;
     * - live record with another fingerprint → throws.
     *
     * When `$operation` throws, nothing is recorded and a retry runs it again.
     *
     * @param string                     $principalId UUID of the caller (see the Presentation decorator for anonymous callers)
     * @param \Closure(): StoredResponse $operation
     *
     * @throws IdempotencyKeyReusedException
     */
    public function execute(
        string $principalId,
        IdempotencyKey $key,
        RequestFingerprint $fingerprint,
        \Closure $operation,
    ): StoredResponse;

    /** Deletes every record of the principal (account deletion); returns how many. */
    public function forgetPrincipal(string $principalId): int;
}
