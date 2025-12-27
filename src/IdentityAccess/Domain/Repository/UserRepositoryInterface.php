<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\UserId;
use App\IdentityAccess\Domain\UserType;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByEmailAndType(string $email, UserType $type): ?User;
}
