<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The way into an ordonnance from the cockpit.
 *
 * It only drafts. Signing is a separate, deliberate act on the ordonnance page:
 * the practitioner decides there how many times it may be renewed and what note
 * goes under the medicines, and only then puts their name on it.
 */
#[Route(
    path: '/consultations/{consultationId}/ordonnance',
    name: 'clinic_prescription_prepare',
    methods: ['GET'],
)]
final class PreparePrescriptionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $consultationId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        $clinicId = $currentClinicId->toString();

        try {
            $prescriptionId = $this->commandBus->dispatch(new PreparePrescriptionForConsultation(
                clinicId: $clinicId,
                consultationId: $consultationId,
                prescriberUserId: $user->id(),
            ));
            \assert(\is_string($prescriptionId));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $consultationId]);
        }

        return $this->redirectToRoute('clinic_prescription_details', ['prescriptionId' => $prescriptionId]);
    }
}
