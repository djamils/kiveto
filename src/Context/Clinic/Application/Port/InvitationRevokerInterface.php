<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Stops the invitation links this clinic sent to a user (IdentityAccess owns
 * them): a member whose access was disabled or ended cannot activate their
 * account through an old e-mail of the clinic.
 */
interface InvitationRevokerInterface
{
    public function revoke(string $userId, string $clinicId): void;
}
