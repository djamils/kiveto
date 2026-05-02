<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
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

        $priority    = (int) $request->request->getString('priority', '0');
        $animalId    = $request->request->getString('animalId') ?: null;
        $animalName  = $request->request->getString('animalName') ?: null;
        $description = $request->request->getString('foundAnimalDescription') ?: null;
        $triageNotes = $request->request->getString('triageNotes') ?: null;

        $triageLevel = 1 === $priority ? 'priority' : 'standard';

        try {
            $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
                clinicId: $currentClinicId->toString(),
                triageLevel: $triageLevel,
                knownAnimalId: $animalId,
                animalName: $animalName,
                provisionalLabel: $description,
                triageNotes: $triageNotes,
            ));

            $this->addFlash('success', 'Patient enregistré en salle d\'attente.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
