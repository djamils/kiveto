<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

/**
 * Platform flows the synchronisation could not process (unparsable, or for a
 * document it cannot find): kept, retried by the next runs, never lost.
 */
interface UnprocessedFlowsInterface
{
    /**
     * @return int how many times the flow could not be processed
     */
    public function record(string $organisationId, string $flowId, string $reason, \DateTimeImmutable $now): int;

    /**
     * Flows of the organisation to retry, oldest first.
     *
     * @return list<string>
     */
    public function toRetry(string $organisationId, int $maxAttempts): array;

    public function resolve(string $organisationId, string $flowId): void;
}
