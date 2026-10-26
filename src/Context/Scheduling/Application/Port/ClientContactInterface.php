<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * How to reach the owner of an appointment by an automated message (Client's
 * notification contact, with its consent rules applied).
 */
interface ClientContactInterface
{
    /** Null when the client does not belong to the clinic. */
    public function contactOf(string $clinicId, string $clientId): ?ClientContact;
}
