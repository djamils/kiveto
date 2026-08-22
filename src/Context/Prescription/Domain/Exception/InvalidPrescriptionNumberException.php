<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

final class InvalidPrescriptionNumberException extends \DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(\sprintf('Invalid prescription number: "%s". Expected ORD-YYYY-NNNNNN.', $value));
    }
}
