<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The journal of the messages Kiveto sent to an owner account (Notification). */
interface PortalRecipientMaskerInterface
{
    /** Erases the recipient of Kiveto's own messages to that phone or e-mail; the clinics' messages stay. */
    public function mask(string $phone, ?string $email): void;
}
