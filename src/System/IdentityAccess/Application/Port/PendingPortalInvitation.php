<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class PendingPortalInvitation
{
    public function __construct(
        public string $invitationId,
        public string $clinicName,
        public ?string $clinicCity,
    ) {
    }
}
