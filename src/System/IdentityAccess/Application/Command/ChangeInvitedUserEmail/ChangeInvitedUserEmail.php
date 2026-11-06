<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeInvitedUserEmail;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Modifier l'e-mail » of an invitation not yet accepted: the PENDING
 * clinic account takes the corrected address. The caller sends the
 * invitations again (each new one replaces the clinic's previous link).
 */
final readonly class ChangeInvitedUserEmail implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }
}
