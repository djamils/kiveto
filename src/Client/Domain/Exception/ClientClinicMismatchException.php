<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientClinicMismatchException extends \DomainException
{
    public function __construct(string $clientId, string $expectedClinicId)
    {
        parent::__construct(\sprintf(
            'Client "%s" does not belong to clinic "%s".',
            $clientId,
            $expectedClinicId,
        ));
    }
}
