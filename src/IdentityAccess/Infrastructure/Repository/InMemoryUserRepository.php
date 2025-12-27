<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Repository;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $byId = [];

    /** @var array<string, string> email -> userId */
    private array $emailIndex = [];

    public function save(User $user): void
    {
        $this->byId[$user->id()->toString()] = $user;
        $this->emailIndex[$user->email()]    = $user->id()->toString();
    }

    public function findById(UserId $id): ?User
    {
        return $this->byId[$id->toString()] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $userId = $this->emailIndex[$email] ?? null;

        return null === $userId ? null : $this->byId[$userId];
    }

    public function findByEmailAndType(string $email, UserType $type): ?User
    {
        $user = $this->findByEmail($email);

        if (null === $user) {
            return null;
        }

        return $user->type()->value === $type->value ? $user : null;
    }
}
