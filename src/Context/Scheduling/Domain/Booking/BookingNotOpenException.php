<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * The clinic takes no online booking: its settings are disabled, or it is not
 * active (suspended, closed, unknown). The API answers 422 `booking_not_open`.
 */
final class BookingNotOpenException extends \DomainException
{
    public function __construct(public readonly string $clinicId)
    {
        parent::__construct('La réservation en ligne n\'est pas ouverte pour cette clinique.');
    }
}
