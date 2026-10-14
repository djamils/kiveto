<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Exception;

/** The patient already has an open admission: it is on site, it cannot arrive twice. */
final class PatientAlreadyAdmittedException extends \DomainException
{
    public function __construct(string $patientId)
    {
        parent::__construct(\sprintf('Patient already has an active admission: %s', $patientId));
    }
}
