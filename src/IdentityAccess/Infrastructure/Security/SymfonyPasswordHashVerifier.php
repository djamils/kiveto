<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class SymfonyPasswordHashVerifier implements PasswordHashVerifierInterface
{
    public function __construct(private PasswordHasherFactoryInterface $hasherFactory)
    {
    }

    public function verify(string $plainPassword, string $passwordHash): bool
    {
        $hasher = $this->hasherFactory->getPasswordHasher(User::class);

        return $hasher->verify($passwordHash, $plainPassword);
    }
}

