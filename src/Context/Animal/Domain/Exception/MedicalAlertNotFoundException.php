<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Exception;

final class MedicalAlertNotFoundException extends \DomainException
{
    public function __construct(string $alertId)
    {
        parent::__construct(\sprintf('Medical alert with ID "%s" not found.', $alertId));
    }
}
