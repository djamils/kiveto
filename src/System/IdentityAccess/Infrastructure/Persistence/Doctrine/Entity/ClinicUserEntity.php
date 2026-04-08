<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class ClinicUserEntity extends UserEntity
{
    public function getType(): UserType
    {
        return UserType::CLINIC;
    }
}
