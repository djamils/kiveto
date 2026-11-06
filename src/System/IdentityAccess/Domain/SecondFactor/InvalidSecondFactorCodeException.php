<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

final class InvalidSecondFactorCodeException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce code n’est pas valide. Vérifie l’heure de ton téléphone et réessaie.');
    }
}
