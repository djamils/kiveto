<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Factory;

use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;

/**
 * @extends AbstractUserFactory<BackofficeUserEntity>
 */
final class BackofficeUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return BackofficeUserEntity::class;
    }
}
