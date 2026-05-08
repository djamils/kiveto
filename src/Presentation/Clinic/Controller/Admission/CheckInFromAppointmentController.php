<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Command\OpenAdmissionFromAppointment\OpenAdmissionFromAppointment;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admission/checkin', name: 'clinic_admission_checkin', methods: ['POST'])]
final class CheckInFromAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('waiting_room_action', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $appointmentId = $request->request->getString('appointmentId') ?: null;
        $animalId      = $request->request->getString('animalId') ?: null;
        $animalName    = $request->request->getString('animalName') ?: null;
        $triageNotes   = $request->request->getString('triageNotes') ?: null;

        try {
            if (null !== $appointmentId) {
                $admissionId = $this->commandBus->dispatch(new OpenAdmissionFromAppointment(
                    clinicId: $currentClinicId->toString(),
                    appointmentId: $appointmentId,
                    knownAnimalId: $animalId,
                    animalName: $animalName,
                    triageNotes: $triageNotes,
                ));

                \assert(\is_string($admissionId));

                $this->commandBus->dispatch(new CheckInAppointment(
                    clinicId: $currentClinicId->toString(),
                    appointmentId: $appointmentId,
                    admissionId: $admissionId,
                ));
            } else {
                $priority    = (int) $request->request->getString('priority', '0');
                $description = $request->request->getString('foundAnimalDescription') ?: null;
                $triageLevel = 1 === $priority ? 'priority' : 'standard';

                $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
                    clinicId: $currentClinicId->toString(),
                    triageLevel: $triageLevel,
                    knownAnimalId: $animalId,
                    animalName: $animalName,
                    provisionalLabel: $description,
                    triageNotes: $triageNotes,
                ));
            }

            $this->addFlash('success', 'Patient enregistré en salle d\'attente.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
