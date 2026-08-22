<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewals;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\SetPrescriptionNotes\SetPrescriptionNotes;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Signing the ordonnance: the renewal allowance and the note are part of the
 * same gesture, since neither can be changed once the document is signed.
 */
#[Route(path: '/ordonnances/{prescriptionId}/emettre', name: 'clinic_prescription_issue', methods: ['POST'])]
final class IssuePrescriptionController extends AbstractController
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

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('prescription_issue_' . $prescriptionId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_prescription_details', ['prescriptionId' => $prescriptionId]);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $notes    = mb_trim($request->request->getString('notes'));

        try {
            $this->commandBus->dispatch(new AllowPrescriptionRenewals(
                clinicId: $clinicId,
                prescriptionId: $prescriptionId,
                count: max(0, $request->request->getInt('renewals')),
            ));

            $this->commandBus->dispatch(new SetPrescriptionNotes(
                clinicId: $clinicId,
                prescriptionId: $prescriptionId,
                notes: '' !== $notes ? $notes : null,
            ));

            $this->commandBus->dispatch(new IssuePrescription($clinicId, $prescriptionId));

            $this->addFlash('success', 'Ordonnance émise.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_prescription_details', ['prescriptionId' => $prescriptionId]);
    }
}
