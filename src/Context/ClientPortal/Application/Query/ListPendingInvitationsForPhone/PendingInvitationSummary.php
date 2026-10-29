<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone;

final readonly class PendingInvitationSummary
{
    public function __construct(
        public string $invitationId,
        public string $clinicName,
        public ?string $city,
    ) {
    }
}
