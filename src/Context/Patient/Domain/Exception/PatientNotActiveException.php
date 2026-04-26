<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Exception;

final class PatientNotActiveException extends \DomainException
{
    public function __construct(string $patientId, string $currentStatus)
    {
        parent::__construct(\sprintf(
            'Patient "%s" is not active (current status: "%s").',
            $patientId,
            $currentStatus,
        ));
    }
}
