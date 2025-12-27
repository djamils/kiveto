<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

final class EmailVerificationRequiredException extends AuthenticationDeniedException
{
    public function __construct()
    {
        parent::__construct('Email is not verified.');
    }

    public function httpStatusCode(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'EMAIL_VERIFICATION_REQUIRED';
    }
}
