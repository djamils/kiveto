<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Context\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment\CreateWaitingRoomEntryFromAppointment as CreateEntryFromAppointment; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/scheduling/appointments/{appointmentId}/check-in',
    name: 'clinic_scheduling_appointment_checkin',
    methods: ['POST'],
)]
final class CheckInAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $appointmentId, Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new CreateEntryFromAppointment(
                appointmentId: $appointmentId,
                arrivalMode: $request->request->getString('arrivalMode', 'STANDARD'),
                priority: $request->request->getInt('priority'),
            ));

            $this->addFlash('success', 'Patient enregistré dans la file d\'attente.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du check-in : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
