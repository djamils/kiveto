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

    /** @var list<string> ids read with a lock, in order */
    public array $locked = [];

    /** An address taken by a concurrent invitation, committed after this request first looked. */
    public ?User $committedMeanwhile = null;

    public function save(User $user): void
    {
        $this->users[$user->id()->toString()] = $user;
    }

    public function addIfEmailFree(User $user): bool
    {
        if (null !== $this->committedMeanwhile) {
            $this->save($this->committedMeanwhile);
            $this->committedMeanwhile = null;
        }

        if (null !== $this->findByEmail($user->email())) {
            return false;
        }

        $this->save($user);

        return true;
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->toString()] ?? null;
    }

    public function findByIdForUpdate(UserId $id): ?User
    {
        $this->locked[] = $id->toString();

        return $this->findById($id);
    }

    public function findByEmailForUpdate(string $email): ?User
    {
        $user = $this->findByEmail($email);

        if (null !== $user) {
            $this->locked[] = $user->id()->toString();
        }

        return $user;
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
