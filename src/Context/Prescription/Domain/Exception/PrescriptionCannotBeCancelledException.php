<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

final class PrescriptionCannotBeCancelledException extends \DomainException
{
    public function __construct(string $prescriptionId, string $reason)
    {
        parent::__construct(\sprintf('Prescription %s cannot be cancelled: %s', $prescriptionId, $reason));
    }
}
