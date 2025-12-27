<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

final class InvalidCredentialsException extends AuthenticationDeniedException
{
    public function __construct()
    {
        parent::__construct('Invalid email or password.');
    }
}
