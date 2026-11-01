<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

final class BookingMotifNotFoundException extends \DomainException
{
    public function __construct(string $motifId)
    {
        parent::__construct(\sprintf('Booking motif "%s" not found.', $motifId));
    }
}
