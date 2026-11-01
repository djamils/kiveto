<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/**
 * What the open requests to close hang on: a clinic client file, an owner
 * account (every clinic), a prescription, a pet or an appointment.
 */
enum RequestSubject: string
{
    case CLIENT       = 'client';
    case USER         = 'user';
    case PRESCRIPTION = 'prescription';
    case PET          = 'pet';
    case APPOINTMENT  = 'appointment';
}
