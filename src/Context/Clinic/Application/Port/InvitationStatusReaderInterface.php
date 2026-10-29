<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Where the members of a clinic stand with their account invitation
 * (IdentityAccess owns the accounts and their invitation links).
 */
interface InvitationStatusReaderInterface
{
    /** Invited by this clinic, link still usable. */
    public const string INVITED = 'PENDING_VALID';

    /** Invited, no usable link of this clinic left: « Renvoyer ». */
    public const string EXPIRED = 'PENDING_EXPIRED';

    public const string ACTIVE = 'ACTIVE';

    /**
     * @param list<string> $userIds
     *
     * @return array<string, string> userId → INVITED | EXPIRED | ACTIVE; unknown and disabled accounts are absent
     */
    public function statuses(string $clinicId, array $userIds): array;
}
