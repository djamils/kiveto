<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterInvitedUser;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Makes sure a clinic account exists for this address before a clinic
 * invites the person; sends nothing (SendInvitation does, once the
 * membership exists). The handler returns an InvitedUserRegistration.
 */
final readonly class RegisterInvitedUser implements CommandInterface
{
    public function __construct(public string $email)
    {
    }
}
