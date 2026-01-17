<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientArchivedCannotBeModifiedException extends \DomainException
{
    public static function forId(string $clientId): self
    {
        return new self(\sprintf('Archived client "%s" cannot be modified.', $clientId));
    }
}
