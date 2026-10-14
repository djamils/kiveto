<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\UpdateAnonymousAdmission\UpdateAnonymousAdmission;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** « Modifier le motif » of a patient waiting: the admission's triage notes. */
#[Route('/admission/{admissionId}/motif', name: 'clinic_admission_update_motif', methods: ['POST'])]
final class UpdateAdmissionMotifController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $admissionId): Response
    {
        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('flux_action', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $motif = trim($request->request->getString('motif'));

        if ('' === $motif) {
            $this->addFlash('error', 'Le motif ne peut pas être vide.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new UpdateAnonymousAdmission(
                clinicId: $currentClinicId->toString(),
                admissionId: $admissionId,
                physicalDescription: null,
                triageNotes: $motif,
            ));

            $this->addFlash('success', 'Motif mis à jour.');
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
