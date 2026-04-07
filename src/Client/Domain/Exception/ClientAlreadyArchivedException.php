<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientAlreadyArchivedException extends \DomainException
{
    public function __construct(string $clientId)
    {
        parent::__construct(\sprintf('Client "%s" is already archived.', $clientId));
    }
}
