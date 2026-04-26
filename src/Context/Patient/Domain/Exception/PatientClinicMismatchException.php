<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

final class PatientClinicMismatchException extends \DomainException
{
    public function __construct(string $patientId, string $expectedClinicId)
    {
        parent::__construct(\sprintf(
            'Patient "%s" does not belong to clinic "%s".',
            $patientId,
            $expectedClinicId,
        ));
    }
}
