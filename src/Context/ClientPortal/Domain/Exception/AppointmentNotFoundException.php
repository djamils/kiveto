<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The appointment is not one of the owner account's in that clinic
 * (unknown, of another client, of a pet it does not see): a 404.
 */
final class AppointmentNotFoundException extends \DomainException
{
    public function __construct(public readonly string $appointmentId)
    {
        parent::__construct(\sprintf('Appointment "%s" is not visible to the owner account.', $appointmentId));
    }
}
