<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Domain\UserType;

final class AuthenticationContextMismatchException extends \RuntimeException
{
    public function __construct(AuthenticationContext $context, UserType $userType)
    {
        parent::__construct(sprintf('User type %s cannot authenticate on %s.', $userType->value, $context->value));
    }
}

