<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** No booking request with that id in the clinic. */
final class BookingRequestNotFoundException extends \DomainException
{
    public function __construct(public readonly string $requestId)
    {
        parent::__construct(\sprintf('Booking request "%s" not found.', $requestId));
    }
}
