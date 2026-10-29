<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/** Short-lived access tokens (JWT) of the Portal API, `sub` = the owner's id. */
interface AccessTokenIssuerInterface
{
    public function issue(string $userId): IssuedAccessToken;
}
