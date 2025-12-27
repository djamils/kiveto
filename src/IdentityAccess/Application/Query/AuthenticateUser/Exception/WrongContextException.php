<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

use App\IdentityAccess\Application\Query\AuthenticateUser\LoginContext;
use App\IdentityAccess\Domain\UserType;

final class WrongContextException extends \RuntimeException
{
    public function __construct(LoginContext $context, UserType $userType)
    {
        parent::__construct(sprintf('User type %s cannot authenticate on %s.', $userType->value, $context->value));
    }
}

