<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Factory;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;

/**
 * @extends AbstractUserFactory<PortalUserEntity>
 */
final class PortalUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return PortalUserEntity::class;
    }
}
