<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * The preventive care a motif stands for, so the Portal can suggest it
 * next to a due vaccination or antiparasitic treatment.
 */
enum BookingPreventiveCareKind: string
{
    case VACCINATION       = 'vaccination';
    case INTERNAL_PARASITE = 'internal_parasite';
    case EXTERNAL_PARASITE = 'external_parasite';
    case OTHER             = 'other';
}
