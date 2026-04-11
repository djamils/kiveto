<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment\CreateWaitingRoomEntryFromAppointment as CreateEntryFromAppointment; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(
    '/scheduling/appointments/{appointmentId}/check-in',
    name: 'clinic_scheduling_appointment_checkin',
    methods: ['POST'],
)]
final class CheckInAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(string $appointmentId, Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $token = new CsrfToken('submit', (string) $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide, veuillez réessayer.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }

        // Cross-clinic IDOR guard: verify the appointment belongs to the active
        // clinic before touching it. Without this, any authenticated user with
        // a valid CSRF token could check-in an appointment from another clinic
        // by guessing or obtaining its UUID.
        $appointment            = $this->queryBus->ask(new GetAppointmentDetails($appointmentId));
        $belongsToCurrentClinic = $appointment instanceof AppointmentDetails
            && $appointment->clinicId === $currentClinicId->toString();
        if (!$belongsToCurrentClinic) {
            $this->addFlash('error', 'Rendez-vous introuvable dans cette clinique.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }

        try {
            $this->commandBus->dispatch(new CreateEntryFromAppointment(
                appointmentId: $appointmentId,
                arrivalMode: $request->request->getString('arrivalMode', 'STANDARD'),
                priority: $request->request->getInt('priority'),
            ));

            $this->addFlash('success', 'Patient enregistré en salle d\'attente.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du check-in : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }
    }
}
