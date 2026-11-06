<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeOwnPassword;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Mot de passe · Modifier » of « Mon compte »: the current password
 * proves the holder. Every session of the account closes (fingerprint).
 */
final readonly class ChangeOwnPassword implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
