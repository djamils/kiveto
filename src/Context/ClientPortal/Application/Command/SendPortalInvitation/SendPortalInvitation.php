<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SendPortalInvitation;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Inviter au Portal » / « Renvoyer l'invitation » / « Réinviter » from the
 * client sheet. Answers the invitation id (the same one on a resend).
 * Refused with ClientNotInvitableException (its `reason` for the sheet).
 */
final readonly class SendPortalInvitation implements CommandInterface
{
    /**
     * @param string $channel `sms` or `email`: how the link is delivered
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public string $actorUserId,
    ) {
    }
}
