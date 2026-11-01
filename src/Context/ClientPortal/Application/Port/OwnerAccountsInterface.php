<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Owner accounts as IdentityAccess knows them. */
interface OwnerAccountsInterface
{
    /** The active owner account, or null (unknown, disabled, deleted). */
    public function accountOf(string $userId): ?OwnerAccount;
}
