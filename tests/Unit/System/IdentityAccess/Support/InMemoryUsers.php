<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

final class InMemoryUsers implements UserRepositoryInterface
{
    /** @var array<string, User> */
    public array $users = [];

    /** @var array<string, \DateTimeImmutable> */
    public array $logins = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->toString()] = $user;
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->toString()] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findByEmailAndType(string $email, UserType $type): ?User
    {
        $user = $this->findByEmail($email);

        return null !== $user && $user->type() === $type ? $user : null;
    }

    public function recordLogin(UserId $id, \DateTimeImmutable $at): void
    {
        $this->logins[$id->toString()] = $at;
    }
}
