<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

/**
 * The client or the animal of an appointment is not one of the clinic's. The
 * message is the same for an unknown id and for one of another clinic: the
 * answer never tells another clinic's records apart.
 */
final class AppointmentPartyNotFoundException extends \DomainException
{
    public static function create(): self
    {
        return new self('Client ou animal introuvable.');
    }
}
