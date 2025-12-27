<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser\Exception;

use App\IdentityAccess\Domain\ValueObject\UserStatus;

final class AccountStatusNotAllowedException extends AuthenticationDeniedException
{
    public function __construct(UserStatus $status)
    {
        parent::__construct(\sprintf('Account status is not allowed (status: %s).', $status->value));
    }

    public function httpStatusCode(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_STATUS_NOT_ALLOWED';
    }
}
