<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Domain\ValueObject\UserType;

interface PasswordHasherInterface
{
    /** The hash to store for this password, with the hasher of this kind of account. */
    public function hash(UserType $type, string $plainPassword): string;
}
