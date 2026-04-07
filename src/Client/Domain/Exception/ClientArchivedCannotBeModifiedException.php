<?php

declare(strict_types=1);

namespace App\Client\Domain\Exception;

final class ClientArchivedCannotBeModifiedException extends \DomainException
{
    public function __construct(string $clientId)
    {
        parent::__construct(\sprintf('Archived client "%s" cannot be modified.', $clientId));
    }
}
