<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Port;

/** What Scheduling says of the clinics' online booking. */
interface OnlineBookingStatusInterface
{
    /**
     * The clinics among these whose online booking takes requests from new
     * clients: settings enabled, new-client booking on, and at least one
     * online motif open to new clients. One call per page.
     *
     * @param list<string> $clinicIds
     *
     * @return list<string>
     */
    public function openToNewClients(array $clinicIds): array;
}
