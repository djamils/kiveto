<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * Where an appointment was booked: at the clinic (VetApp) or online by the
 * client (Portal).
 */
enum AppointmentSource: string
{
    case CLINIC = 'clinic';
    case PORTAL = 'portal';
}
