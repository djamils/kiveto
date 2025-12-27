<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Symfony Security infrastructure adapter.
 *
 * This user provider is responsible for refreshing the authenticated SecurityUser from persistence
 * between requests (session reload). It is "context-aware" by design: it restricts user loading to a
 * single UserType (CLINIC / PORTAL / BACKOFFICE) configured per firewall.
 *
 * This prevents cross-app session refresh and guarantees that a user authenticated in one app
 * cannot be reloaded under another app.
 *
 * Business authentication rules (credentials verification, account policies, email verification, etc.)
 * must remain in the Application use case (AuthenticateUserHandler). This provider only reloads the
 * authenticated user for Symfony's session management.
 */
final readonly class ContextUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserType $type,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findByEmailAndType($identifier, $this->type);

        if (null === $user) {
            throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
        }

        return new SecurityUser(
            id: $user->id()->toString(),
            email: $user->email(),
            type: $user->type(),
            roles: ['ROLE_USER'],
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class || is_subclass_of($class, SecurityUser::class);
    }
}
