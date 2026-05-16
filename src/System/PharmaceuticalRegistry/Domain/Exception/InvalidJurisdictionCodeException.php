<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class InvalidJurisdictionCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('Invalid jurisdiction code: "%s". Must match ^[A-Z]{2,3}$.', $code));
    }
}
