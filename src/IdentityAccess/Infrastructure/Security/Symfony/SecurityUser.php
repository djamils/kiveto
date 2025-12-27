<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Domain\UserType;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string   $id,
        private string   $email,
        private UserType $type,
        private array    $roles = ['ROLE_USER'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
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

