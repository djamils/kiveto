<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The signed-in user kept in the session. It carries a fingerprint of the
 * password hash: when the user reloaded at each request no longer has the
 * same one (password reset), the session is closed.
 */
final readonly class SecurityUser implements UserInterface, EquatableInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $id,
        private string $email,
        private UserType $type,
        private array $roles = ['ROLE_USER'],
        private string $passwordFingerprint = '',
    ) {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // A session opened before the fingerprint existed is closed once.
        if (!new \ReflectionProperty(self::class, 'passwordFingerprint')->isInitialized($this)) {
            return false;
        }

        return $user instanceof self
            && $user->id === $this->id
            && $user->type === $this->type
            && $user->passwordFingerprint === $this->passwordFingerprint;
    }

    public function getUserIdentifier(): string
    {
        $identifier = $this->email;

        if ('' === $identifier) {
            throw new \LogicException('User identifier must not be empty.');
        }

        return $identifier;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // no-op
    }

    public function getPassword(): string
    {
        return '';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): UserType
    {
        return $this->type;
    }
}
