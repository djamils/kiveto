<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\AuthenticateUser\Exception;

use App\System\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

final class AuthenticationContextMismatchException extends AuthenticationDeniedException
{
    public function __construct(AuthenticationContext $context, UserType $userType)
    {
        parent::__construct(\sprintf('User type %s cannot authenticate on %s.', $userType->value, $context->value));
    }

    public function httpStatusCode(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'CONTEXT_MISMATCH';
    }
}
