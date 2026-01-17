<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientAlreadyArchivedException extends \DomainException
{
    public static function forId(string $clientId): self
    {
        return new self(\sprintf('Client "%s" is already archived.', $clientId));
    }
}
