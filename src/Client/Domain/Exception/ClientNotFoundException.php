<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientNotFoundException extends \DomainException
{
    public function __construct(string $clientId)
    {
        parent::__construct(\sprintf('Client with ID "%s" not found.', $clientId));
    }
}
