<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Tells the previous verified address of an owner that the account now
 * uses another one (masked: the notice may reach someone who lost access).
 */
final readonly class PortalEmailChangedNoticeRequest
{
    /**
     * @param string $challengeId    the verification that changed the address (idempotency)
     * @param string $changedAtLabel « 01/11/2026 à 14:05 » (Europe/Paris)
     */
    public function __construct(
        public string $challengeId,
        public string $userId,
        public string $previousEmail,
        public string $maskedNewEmail,
        public string $changedAtLabel,
    ) {
    }
}
