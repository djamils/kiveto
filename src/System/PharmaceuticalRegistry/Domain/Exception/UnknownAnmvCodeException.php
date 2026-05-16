<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class UnknownAnmvCodeException extends \DomainException
{
    public function __construct(int|string $code)
    {
        parent::__construct(\sprintf('Unknown ANMV code: "%s".', $code));
    }
}
