<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail;

use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOwnerAppointmentDetailHandler
{
    public function __construct(
        private OwnerPets $pets,
        private OwnerAppointmentsInterface $appointments,
        private PortalAppointments $views,
    ) {
    }

    public function __invoke(GetOwnerAppointmentDetail $query): ?PortalAppointment
    {
        $appointment = $this->appointments->find($query->clinicId, $query->appointmentId);

        if (null === $appointment) {
            return null;
        }

        $pets = $this->pets->visible($query->userId, $query->clinicId);

        if (!PortalAppointments::belongsTo($appointment, $this->pets->clientIds($query->userId, $query->clinicId), $pets)) {
            return null;
        }

        return $this->views->views($query->clinicId, [$appointment], $pets)[0];
    }
}
