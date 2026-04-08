<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Factory;

use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;

/**
 * @extends AbstractUserFactory<ClinicUserEntity>
 */
final class ClinicUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return ClinicUserEntity::class;
    }
}
