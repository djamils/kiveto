<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

final class PrescriptionHasNoLinesException extends \DomainException
{
    public function __construct(string $prescriptionId)
    {
        parent::__construct(\sprintf('Prescription %s cannot be issued without a single medicine.', $prescriptionId));
    }
}
