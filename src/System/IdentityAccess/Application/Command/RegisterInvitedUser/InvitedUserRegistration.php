<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterInvitedUser;

final readonly class InvitedUserRegistration
{
    /**
     * @param bool $alreadyActive the person already uses Kiveto (another clinic): no invitation is needed
     */
    public function __construct(
        public string $userId,
        public bool $alreadyActive,
    ) {
    }
}
