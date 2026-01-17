<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientClinicMismatchException extends \DomainException
{
    public static function create(string $clientId, string $expectedClinicId): self
    {
        return new self(\sprintf(
            'Client "%s" does not belong to clinic "%s".',
            $clientId,
            $expectedClinicId,
        ));
    }
}
