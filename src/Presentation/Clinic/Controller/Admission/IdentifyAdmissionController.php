<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatient;
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

#[Route('/admission/identify', name: 'clinic_admission_identify', methods: ['POST'])]
final class IdentifyAdmissionController extends AbstractController
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
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_identify', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId    = $currentClinicId->toString();
        $admissionId = $request->request->getString('admissionId');
        $tab         = $request->request->getString('tab');

        try {
            if ('owner' === $tab) {
                $animalId   = $request->request->getString('animalId');
                $animalName = $request->request->getString('animalName');

                $this->commandBus->dispatch(new ReconcileAdmissionPatient(
                    clinicId: $clinicId,
                    admissionId: $admissionId,
                    animalId: $animalId,
                    animalName: $animalName,
                ));

                $this->addFlash('success', 'Animal identifié avec succès.');
            } elseif ('anon' === $tab) {
                $physicalDescription = $request->request->getString('physicalDescription') ?: null;
                $triageNotes         = $request->request->getString('triageNotes') ?: null;

                $this->commandBus->dispatch(new UpdateAnonymousAdmission(
                    clinicId: $clinicId,
                    admissionId: $admissionId,
                    physicalDescription: $physicalDescription,
                    triageNotes: $triageNotes,
                ));

                $this->addFlash('success', 'Informations mises à jour.');
            }
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
