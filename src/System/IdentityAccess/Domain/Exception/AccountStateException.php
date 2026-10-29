<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

use App\System\IdentityAccess\Domain\ValueObject\UserStatus;

/** A lifecycle step asked of an account in a status that does not allow it. */
final class AccountStateException extends \DomainException
{
    public static function notPending(UserStatus $status): self
    {
        return new self(\sprintf('Only a PENDING account can be activated (status %s).', $status->value));
    }

    public static function notActive(UserStatus $status): self
    {
        return new self(\sprintf('Only an ACTIVE account can change its password (status %s).', $status->value));
    }
}
