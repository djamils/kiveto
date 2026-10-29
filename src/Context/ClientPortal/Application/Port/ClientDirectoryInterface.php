<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** What ClientPortal reads of a clinic client (Client context). */
interface ClientDirectoryInterface
{
    /** How to reach the client; null when the client does not belong to the clinic. */
    public function contactOf(string $clinicId, string $clientId): ?PortalClientContact;

    /** Identity and postal address of the client; null when unknown to the clinic. */
    public function snapshotOf(string $clinicId, string $clientId): ?PortalClientSnapshot;
}
