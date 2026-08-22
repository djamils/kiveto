<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescription;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '/ordonnances/{prescriptionId}/annuler', name: 'clinic_prescription_cancel', methods: ['POST'])]
final class CancelPrescriptionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $prescriptionId): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('prescription_cancel_' . $prescriptionId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_prescription_details', ['prescriptionId' => $prescriptionId]);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $reason = mb_trim($request->request->getString('reason'));

        try {
            $this->commandBus->dispatch(new CancelPrescription(
                clinicId: $currentClinicId->toString(),
                prescriptionId: $prescriptionId,
                reason: '' !== $reason ? $reason : 'Annulation par le praticien',
            ));

            $this->addFlash('success', 'Ordonnance annulée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_prescription_details', ['prescriptionId' => $prescriptionId]);
    }
}
