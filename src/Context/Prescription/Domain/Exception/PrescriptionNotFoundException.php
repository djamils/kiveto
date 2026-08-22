<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

final class PrescriptionNotFoundException extends \DomainException
{
    public function __construct(string $prescriptionId)
    {
        parent::__construct(\sprintf('Prescription not found: %s', $prescriptionId));
    }
}
