<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The clinic's booking motifs (Scheduling context). */
interface BookingMotifsInterface
{
    /**
     * The motifs open online and not archived, in the clinic's order.
     *
     * @return list<OnlineMotif>
     */
    public function onlineMotifs(string $clinicId): array;
}
