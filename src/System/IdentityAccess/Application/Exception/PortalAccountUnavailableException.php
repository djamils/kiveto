<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Exception;

/** The signed-in owner account is no longer an active owner account with a phone. */
final class PortalAccountUnavailableException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce compte n\'est plus disponible.');
    }
}
