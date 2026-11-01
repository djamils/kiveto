<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The prescription cannot be renewed by its owner (S6c). The API answers
 * 422 `prescription_not_renewable` with the reason.
 */
final class PrescriptionNotRenewableException extends \DomainException
{
    /** Cancelled, or carrying a controlled substance. */
    public const string NOT_RENEWABLE = 'not_renewable';

    /** Its pet is deceased, missing, or no longer known. */
    public const string PET_NOT_ALIVE = 'pet_not_alive';

    /** A renewal of it was refused less than 7 days ago. */
    public const string RECENTLY_REJECTED = 'recently_rejected';

    public function __construct(public readonly string $prescriptionId, public readonly string $reason)
    {
        parent::__construct('Cette ordonnance ne peut pas être renouvelée en ligne.');
    }
}
