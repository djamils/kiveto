<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\SendInvitation;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Envoyer l'invitation » / « Renvoyer »: a new 72-hour link of this clinic
 * (its previous one is invalidated, other clinics' links are not) and the
 * e-mail carrying it. Every display value comes from the caller, resolved
 * through Clinic queries. Nothing is sent to an account that is not PENDING.
 * The handler returns the token id, or null when nothing was sent.
 */
final readonly class SendInvitation implements CommandInterface
{
    /**
     * @param string $inviterPrintedName « Dr Claire Lambert »
     * @param string $inviteeFirstName   « Hugo », or ''
     * @param string $roleLabel          « Vétérinaire »
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $inviterPrintedName,
        public string $clinicName,
        public string $clinicCity,
        public string $inviteeFirstName,
        public string $roleLabel,
    ) {
    }
}
