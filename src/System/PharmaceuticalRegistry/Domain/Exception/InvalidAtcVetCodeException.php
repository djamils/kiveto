<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class InvalidAtcVetCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('Invalid ATCvet code: "%s". Must match ^Q[A-Z]\\d{2}[A-Z\\d]{0,4}$.', $code));
    }
}
