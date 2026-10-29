<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Notification context: to be called last in the transaction. */
interface NotificationRequesterInterface
{
    public function requestPortalInvitation(PortalInvitationMessage $message): void;
}
