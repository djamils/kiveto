<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/** The clinic account behind the address is disabled: it cannot be invited again. */
final class AccountDisabledException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce compte est désactivé. Contactez le support Kiveto.');
    }
}
