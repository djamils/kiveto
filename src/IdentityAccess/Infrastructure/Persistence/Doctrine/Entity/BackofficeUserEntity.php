<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use App\IdentityAccess\Domain\ValueObject\UserType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class BackofficeUserEntity extends UserEntity
{
    public function getType(): UserType
    {
        return UserType::BACKOFFICE;
    }
}
