<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Repository;

use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByEmailAndType(string $email, UserType $type): ?User;
}
