<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

/**
 * Which transmissions are due a submission, and the row-by-row marker that
 * makes sure each one is sent once per window even when releases and
 * catch-ups run concurrently.
 */
interface SubmissionScheduleInterface
{
    /**
     * Transmissions of the organisation still waiting for a connection,
     * invoices before credit notes.
     *
     * @return list<string> transmission ids
     */
    public function preparedWithoutConnection(string $organisationId): array;

    /**
     * Transmissions with a connection, still prepared or submitting, whose last
     * request is older than $requestedBefore (or never made), invoices first.
     *
     * @return list<string> transmission ids
     */
    public function staleRequested(\DateTimeImmutable $requestedBefore): array;

    /**
     * Organisations having transmissions that still wait for a connection.
     *
     * @return list<string> SIRENs
     */
    public function organisationsWaitingForConnection(): array;

    /**
     * Marks each transmission as requested now (counting the attempt) when
     * it was not requested since $requestedBefore.
     *
     * @param list<string> $transmissionIds
     *
     * @return list<string> the transmissions this call marked
     */
    public function markSubmissionRequested(array $transmissionIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): array;
}
