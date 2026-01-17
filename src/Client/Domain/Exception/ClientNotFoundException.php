<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientNotFoundException extends \DomainException
{
    public static function forId(string $clientId): self
    {
        return new self(\sprintf('Client with ID "%s" not found.', $clientId));
    }
}
