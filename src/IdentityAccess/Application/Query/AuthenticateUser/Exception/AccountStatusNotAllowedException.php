<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

use App\IdentityAccess\Domain\UserStatus;

final class AccountStatusNotAllowedException extends \RuntimeException
{
    public function __construct(UserStatus $status)
    {
        parent::__construct(sprintf('User is not active (status: %s).', $status->value));
    }
}

