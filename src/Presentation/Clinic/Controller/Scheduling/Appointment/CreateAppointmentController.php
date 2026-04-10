<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/appointments/create', name: 'clinic_scheduling_appointment_create', methods: ['POST'])]
final class CreateAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $startsAtRaw = $request->request->getString('startsAtUtc', 'now');
            $startsAt    = new \DateTimeImmutable($startsAtRaw);

            // Convert empty strings to null for optional fields.
            $ownerId            = $request->request->getString('ownerId') ?: null;
            $animalId           = $request->request->getString('animalId') ?: null;
            $practitionerUserId = $request->request->getString('practitionerUserId') ?: null;
            $reason             = $request->request->getString('reason') ?: null;
            $notes              = $request->request->getString('notes') ?: null;

            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: $currentClinicId->toString(),
                ownerId: $ownerId,
                animalId: $animalId,
                practitionerUserId: $practitionerUserId,
                startsAtUtc: $startsAt,
                durationMinutes: $request->request->getInt('durationMinutes', 30),
                reason: $reason,
                notes: $notes,
            ));

            $this->addFlash('success', 'Rendez-vous créé avec succès.');

            return $this->redirectToRoute('clinic_scheduling_agenda');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du rendez-vous : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_agenda');
        }
    }
}
