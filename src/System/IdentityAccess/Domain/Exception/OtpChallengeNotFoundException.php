<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/** No challenge to send a new code for: unknown, or already used. */
final class OtpChallengeNotFoundException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce code de connexion n\'existe pas ou a déjà été utilisé.');
    }
}
