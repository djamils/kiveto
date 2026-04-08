<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity as DoctrineUser;

final class DoctrineUserFactory
{
    public function createForType(UserType $type): DoctrineUser
    {
        return match ($type) {
            UserType::CLINIC     => new ClinicUserEntity(),
            UserType::PORTAL     => new PortalUserEntity(),
            UserType::BACKOFFICE => new BackofficeUserEntity(),
        };
    }
}
