<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Repository;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\UserId;

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
}
