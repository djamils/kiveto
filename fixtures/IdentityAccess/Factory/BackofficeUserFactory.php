<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Factory;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;

/**
 * @extends AbstractUserFactory<BackofficeUser>
 */
final class BackofficeUserFactory extends AbstractUserFactory
{
    public static function class(): string
    {
        return BackofficeUser::class;
    }
}
