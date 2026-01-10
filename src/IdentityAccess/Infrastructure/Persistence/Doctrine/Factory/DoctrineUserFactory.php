<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory;

use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity as DoctrineUser;

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
