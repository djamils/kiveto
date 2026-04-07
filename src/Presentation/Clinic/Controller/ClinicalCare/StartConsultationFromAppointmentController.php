<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointment;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class StartConsultationFromAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/start-from-appointment/{appointmentId}', name: 'clinic_consultation_start_from_appointment', methods: ['POST'])]
    public function __invoke(string $appointmentId): Response
    {
        /** @var SecurityUser $user */
        $user = $this->getUser();
        \assert(null !== $user);

        try {
            $consultationId = $this->commandBus->dispatch(
                new StartConsultationFromAppointment(
                    appointmentId: $appointmentId,
                    startedByUserId: $user->id(),
                )
            );

            $this->addFlash('success', 'Consultation démarrée avec succès.');

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $consultationId]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du démarrage de la consultation : ' . $e->getMessage());

            return $this->redirectToRoute('clinic_scheduling_dashboard');
        }
    }
}
