<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Client's mobile number rule, the single one shared by Portal invitations
 * and the Portal sign-in: only these numbers receive a code.
 */
interface MobilePhonePolicyInterface
{
    public function isPortalMobile(string $phone): bool;
}
