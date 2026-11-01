<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * Who cancelled an appointment: the clinic (VetApp) or the client (Portal).
 */
enum CancellationActor: string
{
    case CLINIC = 'clinic';
    case CLIENT = 'client';
}
