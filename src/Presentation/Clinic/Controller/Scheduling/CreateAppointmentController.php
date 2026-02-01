<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
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

        $data = $request->request->all();

        try {
            $startsAt = new \DateTimeImmutable($data['startsAtUtc'] ?? 'now');

            // Convert empty strings to null for optional UUID fields
            $ownerId = !empty($data['ownerId']) ? $data['ownerId'] : null;
            $animalId = !empty($data['animalId']) ? $data['animalId'] : null;
            $practitionerUserId = !empty($data['practitionerUserId']) ? $data['practitionerUserId'] : null;
            $reason = !empty($data['reason']) ? $data['reason'] : null;
            $notes = !empty($data['notes']) ? $data['notes'] : null;

            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: $currentClinicId->toString(),
                ownerId: $ownerId,
                animalId: $animalId,
                practitionerUserId: $practitionerUserId,
                startsAtUtc: $startsAt,
                durationMinutes: (int) ($data['durationMinutes'] ?? 30),
                reason: $reason,
                notes: $notes,
            ));

            $this->addFlash('success', 'Rendez-vous créé avec succès.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du rendez-vous : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
