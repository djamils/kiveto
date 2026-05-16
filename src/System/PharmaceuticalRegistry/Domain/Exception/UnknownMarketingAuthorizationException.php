<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class UnknownMarketingAuthorizationException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Marketing authorization not found: "%s".', $id));
    }
}
