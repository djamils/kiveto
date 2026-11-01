<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * Whether an online booking of a known client is confirmed at once (`auto`)
 * or waits for the clinic (`manual`). A new client always waits (D15).
 */
enum ConfirmationMode: string
{
    case AUTO   = 'auto';
    case MANUAL = 'manual';
}
