<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientMustHaveAtLeastOneContactMethodException extends \DomainException
{
    public static function create(): self
    {
        return new self('Client must have at least one contact method (phone or email).');
    }
}
