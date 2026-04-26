<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

final class PatientNotFoundException extends \DomainException
{
    public function __construct(string $patientId)
    {
        parent::__construct(\sprintf('Patient with ID "%s" not found.', $patientId));
    }
}
