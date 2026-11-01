<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentCalendar;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The `.ics` of an appointment of the clinic that still holds its slot
 * (confirmed or pending) → ?string, null when unknown, of another clinic,
 * withdrawn or terminal. The caller checks it belongs to the owner; the
 * free-text reason and notes are never read.
 */
final readonly class GetAppointmentCalendar implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
    ) {
    }
}
