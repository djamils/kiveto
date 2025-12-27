<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class PasswordHashVerifier implements PasswordHashVerifierInterface
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
