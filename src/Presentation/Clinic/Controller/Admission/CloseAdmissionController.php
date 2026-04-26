<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Domain\Exception\AdmissionAlreadyClosedException;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admission/{admissionId}/close', name: 'clinic_admission_close', methods: ['POST'])]
final class CloseAdmissionController extends AbstractController
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
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_close_' . $admissionId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $closureReason = $request->request->getString('closure_reason');

        if ('' === $closureReason) {
            $this->addFlash('error', 'La raison de clôture est obligatoire.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        try {
            $this->commandBus->dispatch(new CloseAdmission(
                clinicId: $currentClinicId->toString(),
                admissionId: $admissionId,
                closureReason: $closureReason,
            ));

            $this->addFlash('success', 'Admission clôturée avec succès.');
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (AdmissionAlreadyClosedException) {
            $this->addFlash('error', 'Cette admission est déjà clôturée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\ValueError) {
            $this->addFlash('error', 'Raison de clôture invalide.');
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
