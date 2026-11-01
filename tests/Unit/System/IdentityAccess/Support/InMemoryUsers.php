<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\Shared\Domain\ValueObject\PhoneNumber;
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

    /** @var list<string> phones whose claim mutex was taken, in order */
    public array $lockedPhones = [];

    /** @var list<string> phones whose mutex row was deleted, in order */
    public array $deletedPhoneLocks = [];

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

        if (null !== $user->email() && null !== $this->findByEmailAndType($user->email(), $user->type())) {
            return false;
        }

        $this->save($user);

        return true;
    }

    public function addPortalIfPhoneFree(User $user): bool
    {
        if (null !== $this->committedMeanwhile) {
            $this->save($this->committedMeanwhile);
            $this->committedMeanwhile = null;
        }

        $phoneTaken = null !== $user->phone() && null !== $this->findByPhoneAndType($user->phone(), $user->type());
        $emailTaken = null !== $user->email() && null !== $this->findByEmailAndType($user->email(), $user->type());

        if ($phoneTaken || $emailTaken) {
            return false;
        }

        $this->save($user);

        return true;
    }

    public function lockPortalPhone(PhoneNumber $phone): void
    {
        $this->lockedPhones[] = $phone->toString();
    }

    public function deletePortalPhoneLock(PhoneNumber $phone): void
    {
        $this->deletedPhoneLocks[] = $phone->toString();
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

    public function findByEmailAndTypeForUpdate(string $email, UserType $type): ?User
    {
        $user = $this->findByEmailAndType($email, $type);

        if (null !== $user) {
            $this->locked[] = $user->id()->toString();
        }

        return $user;
    }

    public function findByEmailAndType(string $email, UserType $type): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === $email && $user->type() === $type) {
                return $user;
            }
        }

        return null;
    }

    public function findByPhoneAndType(PhoneNumber $phone, UserType $type): ?User
    {
        foreach ($this->users as $user) {
            if (null !== $user->phone() && $user->phone()->equals($phone) && $user->type() === $type) {
                return $user;
            }
        }

        return null;
    }

    public function findByPhoneAndTypeForUpdate(PhoneNumber $phone, UserType $type): ?User
    {
        $user = $this->findByPhoneAndType($phone, $type);

        if (null !== $user) {
            $this->locked[] = $user->id()->toString();
        }

        return $user;
    }

    public function recordLogin(UserId $id, \DateTimeImmutable $at): void
    {
        $this->logins[$id->toString()] = $at;
    }
}
