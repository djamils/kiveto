<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOwnerAppointment;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One appointment of the clinic as its owner sees it on the Portal →
 * ?PortalAppointmentItem (null when unknown or of another clinic). The
 * caller checks it belongs to the owner; the free-text reason and notes
 * are never read.
 */
final readonly class GetOwnerAppointment implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
    ) {
    }
}
