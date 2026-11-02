<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The journal of the messages Kiveto sent to an owner account (Notification). */
interface PortalRecipientMaskerInterface
{
    /**
     * Erases the recipient of Kiveto's own messages of that account — or to
     * that phone or e-mail —, earlier addresses of the account included; the
     * clinics' messages stay.
     */
    public function mask(string $userId, string $phone, ?string $email): void;
}
