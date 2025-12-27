<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use App\IdentityAccess\Domain\UserType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class PortalUser extends User
{
    public function getType(): UserType
    {
        return UserType::PORTAL;
    }
}

