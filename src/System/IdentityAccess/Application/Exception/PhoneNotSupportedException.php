<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Exception;

/** The number is not a mobile the Portal serves (MobileNumberPolicy). */
final class PhoneNotSupportedException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce numéro de mobile n\'est pas encore pris en charge par le Portal.');
    }
}
