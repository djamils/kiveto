<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Exception;

final class ManualCommunicationCannotChangeException extends \DomainException
{
    public function __construct(string $communicationId)
    {
        parent::__construct(\sprintf('Communication "%s" was logged by the staff and cannot change.', $communicationId));
    }
}
