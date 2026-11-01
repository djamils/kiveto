<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The prescription is not a listed document of the account in that clinic: a 404. */
final class PrescriptionNotFoundException extends \DomainException
{
    public function __construct(public readonly string $prescriptionId)
    {
        parent::__construct(\sprintf('Prescription "%s" is not visible to the owner account.', $prescriptionId));
    }
}
