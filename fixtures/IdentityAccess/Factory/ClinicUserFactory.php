<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Factory;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;

/**
 * @extends AbstractUserFactory<ClinicUser>
 */
final class ClinicUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return ClinicUser::class;
    }
}
