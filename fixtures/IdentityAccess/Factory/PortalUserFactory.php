<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Factory;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;

/**
 * @extends AbstractUserFactory<PortalUser>
 */
final class PortalUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return PortalUser::class;
    }
}
