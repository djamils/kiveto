<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The Portal phone rule (Client's MobileNumberPolicy): the only numbers that
 * can be invited are those the Portal API accepts for a sign-in code.
 */
interface MobilePhonePolicyInterface
{
    /** @param string $phone E.164 */
    public function isPortalMobile(string $phone): bool;
}
