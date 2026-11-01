<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One appointment of the account in the clinic → ?PortalAppointment, null
 * when unknown or not the account's (neither of its client files nor of a
 * pet it sees).
 */
final readonly class GetOwnerAppointmentDetail implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $appointmentId,
    ) {
    }
}
