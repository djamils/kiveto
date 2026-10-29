<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/** The address belongs to a portal or back-office account: it cannot be invited to a clinic. */
final class EmailUsedByAnotherAccountException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cette adresse est déjà utilisée par un autre compte Kiveto.');
    }
}
