<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\System\IdentityAccess\Application\Port\PasswordHasherInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** The hasher configured for the account entities (security.yaml). */
final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private DoctrineUserFactory $entities,
    ) {
    }

    public function hash(UserType $type, #[\SensitiveParameter] string $plainPassword): string
    {
        return $this->hasher->hashPassword($this->entities->createForType($type), $plainPassword);
    }
}
