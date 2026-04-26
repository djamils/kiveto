<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionTriage\UpdateAdmissionTriage;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admission/{admissionId}/triage', name: 'clinic_admission_update_triage', methods: ['POST'])]
final class UpdateAdmissionTriageController extends AbstractController
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
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_triage_' . $admissionId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $newTriageLevel = $request->request->getString('triage_level');

        if ('' === $newTriageLevel) {
            $this->addFlash('error', 'Le niveau de triage est obligatoire.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        try {
            $this->commandBus->dispatch(new UpdateAdmissionTriage(
                clinicId: $currentClinicId->toString(),
                admissionId: $admissionId,
                newTriageLevel: $newTriageLevel,
            ));

            $this->addFlash('success', 'Niveau de triage mis à jour.');
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\ValueError) {
            $this->addFlash('error', 'Niveau de triage invalide.');
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
