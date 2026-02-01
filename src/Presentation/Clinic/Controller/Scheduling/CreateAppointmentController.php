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

            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: $currentClinicId->toString(),
                ownerId: $data['ownerId'] ?? null,
                animalId: $data['animalId'] ?? null,
                practitionerUserId: $data['practitionerUserId'] ?? null,
                startsAtUtc: $startsAt,
                durationMinutes: (int) ($data['durationMinutes'] ?? 30),
                reason: $data['reason'] ?? null,
                notes: $data['notes'] ?? null,
            ));

            $this->addFlash('success', 'Rendez-vous créé avec succès.');

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la création du rendez-vous : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
