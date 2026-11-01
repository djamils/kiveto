<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Presentation\Clinic\ViewModel\Agenda\PendingRequestEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Refuser » an online request waiting for the clinic (agenda card or
 * dashboard task): the slot is freed and the client is told.
 */
#[Route(
    '/scheduling/appointments/{appointmentId}/decline',
    name: 'clinic_scheduling_appointment_decline',
    methods: ['POST'],
)]
final readonly class DeclineAppointmentController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PendingRequestEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $appointmentId): Response
    {
        return $this->endpoint->handle(
            $request,
            $appointmentId,
            fn (string $clinicId): mixed => $this->commandBus->dispatch(new DeclineAppointment($appointmentId, $clinicId)),
            'Demande refusée — le client est prévenu.',
        );
    }
}
