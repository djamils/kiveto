<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Verification;

/**
 * Finds, across every clinic, the professional profiles due for a new
 * verification, and marks them requested so that they are queued once.
 */
interface TaxProfileVerificationScheduleInterface
{
    /**
     * Active French businesses never checked, whose last answer was an outage,
     * or whose company check is older than $checkedBefore — skipping those
     * requested after $requestedBefore. Never-checked profiles first.
     *
     * @return list<array{clinicId: string, clientId: string}>
     */
    public function findStale(\DateTimeImmutable $checkedBefore, \DateTimeImmutable $requestedBefore): array;

    /**
     * Sets the request date of the given clients still not requested after
     * $requestedBefore, and returns the ids actually marked.
     *
     * @param list<string> $clientIds
     *
     * @return list<string>
     */
    public function markRequested(array $clientIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): array;
}
