<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Exception;

final class PrescriberCannotSignException extends \DomainException
{
    public function __construct(string $displayName)
    {
        parent::__construct(\sprintf(
            'Prescriber "%s" has no registration number and cannot sign a prescription.',
            $displayName,
        ));
    }
}
