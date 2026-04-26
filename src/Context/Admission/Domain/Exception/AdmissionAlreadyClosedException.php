<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Exception;

final class AdmissionAlreadyClosedException extends \DomainException
{
    public function __construct(string $admissionId)
    {
        parent::__construct(\sprintf('Admission is already closed: %s', $admissionId));
    }
}
