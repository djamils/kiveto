<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\Shared\Domain\ValueObject\PhoneNumber;

/**
 * ClientPortal's pending, unexpired invitations of a phone: they make the
 * phone known (no unknown-number budget) and earn its owner an onboarding
 * grant when a sign-in code is verified.
 */
interface PendingPortalInvitationsInterface
{
    /**
     * @return list<PendingPortalInvitation>
     */
    public function listForPhone(PhoneNumber $phone): array;
}
