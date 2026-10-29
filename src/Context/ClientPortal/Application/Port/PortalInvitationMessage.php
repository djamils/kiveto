<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** One send of an invitation (first send or resend), ready to print. */
final readonly class PortalInvitationMessage
{
    /**
     * @param string $channel          `sms` or `email`
     * @param string $recipientAddress E.164 number or e-mail address
     * @param string $link             the one-time link, token in clear
     * @param string $expiresOn        « 05/11/2026 », Europe/Paris
     */
    public function __construct(
        public string $invitationId,
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public string $recipientAddress,
        public string $recipientName,
        public string $clinicName,
        public string $ownerFirstName,
        #[\SensitiveParameter]
        public string $link,
        public string $expiresOn,
        public \DateTimeImmutable $sentAt,
    ) {
    }
}
