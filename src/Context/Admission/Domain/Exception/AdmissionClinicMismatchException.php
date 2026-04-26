<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Exception;

final class AdmissionClinicMismatchException extends \DomainException
{
    public function __construct(string $admissionId, string $expectedClinicId)
    {
        parent::__construct(\sprintf(
            'Admission "%s" does not belong to clinic "%s".',
            $admissionId,
            $expectedClinicId,
        ));
    }
}
