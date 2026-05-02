<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Exception;

final class AuthorityNotificationNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('AuthorityNotification "%s" not found.', $id));
    }
}
