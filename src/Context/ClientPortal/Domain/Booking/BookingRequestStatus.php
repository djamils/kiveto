<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

enum BookingRequestStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case DECLINED  = 'declined';
}
