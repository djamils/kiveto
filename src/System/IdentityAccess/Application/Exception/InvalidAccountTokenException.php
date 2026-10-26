<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Exception;

/** Unknown, expired, already used or replaced link — or one of another kind. */
final class InvalidAccountTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce lien n’est plus valide.');
    }
}
