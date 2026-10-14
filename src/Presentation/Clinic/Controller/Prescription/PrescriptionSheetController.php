<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Presentation\Clinic\ViewModel\Prescription\PrescriptionPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The side sheet of one ordonnance, as JSON: a click on a row of the register
 * opens it without leaving the page.
 */
#[Route(path: '/prescriptions/{prescriptionId}/sheet', name: 'clinic_prescription_sheet', methods: ['GET'])]
final class PrescriptionSheetController extends AbstractController
{
    public function __construct(
        private readonly PrescriptionPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $prescriptionId): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $sheet = $this->pages->sheet($currentClinicId->toString(), $prescriptionId);

        if (null === $sheet) {
            return new JsonResponse(['error' => 'Ordonnance introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($sheet);
    }
}
