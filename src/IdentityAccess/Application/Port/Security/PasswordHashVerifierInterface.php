<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port\Security;

interface PasswordHashVerifierInterface
{
    public function verify(string $plainPassword, string $passwordHash): bool;
}
