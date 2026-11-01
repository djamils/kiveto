<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Context\ClientPortal\Domain\Exception\ConsentConflictException;

/** Writes the consents of the clinic's client files (Client context). */
interface ClientConsentWriterInterface
{
    /**
     * Sets the channels of one consent kind of a client file, written as the
     * owner's own change (source `portal`, the PORTAL user as author). Runs in
     * the caller's transaction.
     *
     * @param list<string> $channels
     *
     * @throws ConsentConflictException when the file changed concurrently
     */
    public function setConsent(string $clinicId, string $clientId, string $kind, array $channels, string $userId, string $authorLabel): void;
}
