<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

abstract class AuthenticationDeniedException extends \RuntimeException
{
    public function httpStatusCode(): int
    {
        return 401;
    }

    abstract public function errorCode(): string;
}
