<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory;

use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User as DoctrineUser;

final class DoctrineUserFactory
{
    public function createForType(UserType $type): DoctrineUser
    {
        return match ($type) {
            UserType::CLINIC     => new ClinicUser(),
            UserType::PORTAL     => new PortalUser(),
            UserType::BACKOFFICE => new BackofficeUser(),
        };
    }
}
