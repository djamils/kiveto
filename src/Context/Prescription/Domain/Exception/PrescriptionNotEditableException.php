<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;

final class PrescriptionNotEditableException extends \DomainException
{
    public function __construct(string $prescriptionId, PrescriptionStatus $status)
    {
        parent::__construct(\sprintf(
            'Prescription %s can no longer be edited: it is %s.',
            $prescriptionId,
            $status->value,
        ));
    }
}
