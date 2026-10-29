<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Whether the accounts of staff members are active (IdentityAccess answers):
 * an invited member who never activated their account does not count as an
 * active veterinarian.
 */
interface UserAccountStatusInterface
{
    /**
     * @param list<string> $userIds
     *
     * @return list<string> those of the users whose account is ACTIVE
     */
    public function activeUserIds(array $userIds): array;
}
