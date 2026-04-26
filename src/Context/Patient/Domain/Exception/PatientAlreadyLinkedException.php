<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

final class PatientAlreadyLinkedException extends \DomainException
{
    public function __construct(string $patientId)
    {
        parent::__construct(\sprintf('Patient "%s" is already linked to an animal.', $patientId));
    }
}
