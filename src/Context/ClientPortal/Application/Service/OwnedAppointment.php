<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\OwnerAppointment;

/** An appointment of the account and the client file it reaches it through. */
final readonly class OwnedAppointment
{
    public function __construct(
        public OwnerAppointment $appointment,
        public string $clientId,
    ) {
    }
}
