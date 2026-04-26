<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admission/walkin', name: 'clinic_admission_walkin', methods: ['POST'])]
final class WalkInAdmissionController extends AbstractController
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

        $arrivalMode = $request->request->getString('arrivalMode');
        $priority    = (int) $request->request->getString('priority', '0');
        $animalId    = $request->request->getString('animalId') ?: null;
        $animalName  = $request->request->getString('animalName') ?: null;
        $description = $request->request->getString('foundAnimalDescription') ?: null;
        $triageNotes = $request->request->getString('triageNotes') ?: null;

        $intakeChannel = 'EMERGENCY' === $arrivalMode ? 'emergency' : 'walk_in';

        $triageLevel = match (true) {
            $priority >= 2  => 'emergency',
            1 === $priority => 'priority',
            default         => 'standard',
        };

        try {
            $this->commandBus->dispatch(new OpenEmergencyAdmission(
                clinicId: $currentClinicId->toString(),
                intakeChannel: $intakeChannel,
                triageLevel: $triageLevel,
                provisionalLabel: $description,
                knownAnimalId: $animalId,
                animalName: $animalName,
                triageNotes: $triageNotes,
            ));

            $label = 'EMERGENCY' === $arrivalMode
                ? 'Urgence enregistrée en salle d\'attente.'
                : 'Patient enregistré en salle d\'attente.';

            $this->addFlash('success', $label);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
