<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;

/**
 * The status an owner sees on the Portal. A cancellation is told apart by
 * who made it; a cancellation without a recorded actor predates the Portal
 * and was the clinic's.
 */
enum PortalAppointmentStatus: string
{
    case CONFIRMED                   = 'confirmed';
    case PENDING_CLINIC_CONFIRMATION = 'pending_clinic_confirmation';
    case CANCELLED_BY_CLIENT         = 'cancelled_by_client';
    case CANCELLED_BY_CLINIC         = 'cancelled_by_clinic';
    case DECLINED                    = 'declined';
    case COMPLETED                   = 'completed';
    case NO_SHOW                     = 'no_show';

    public static function of(AppointmentStatus $status, ?CancellationActor $cancelledBy): self
    {
        return match ($status) {
            AppointmentStatus::PLANNED                     => self::CONFIRMED,
            AppointmentStatus::PENDING_CLINIC_CONFIRMATION => self::PENDING_CLINIC_CONFIRMATION,
            AppointmentStatus::CANCELLED                   => CancellationActor::CLIENT === $cancelledBy ? self::CANCELLED_BY_CLIENT : self::CANCELLED_BY_CLINIC,
            AppointmentStatus::DECLINED                    => self::DECLINED,
            AppointmentStatus::COMPLETED                   => self::COMPLETED,
            AppointmentStatus::NO_SHOW                     => self::NO_SHOW,
        };
    }
}
