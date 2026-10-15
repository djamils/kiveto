<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Presentation\Clinic\ViewModel\Consultation\ConsultationRegisterPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The side sheet of one consultation of the register, as JSON. A
 * consultation of another clinic is not found.
 */
#[Route(
    path: '/consultations/{consultationId}/sheet',
    name: 'clinic_consultation_sheet',
    requirements: ['consultationId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ConsultationSheetController extends AbstractController
{
    public function __construct(
        private readonly ConsultationRegisterPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $consultationId): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $sheet = $this->pages->sheet($currentClinicId->toString(), $consultationId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Consultation introuvable.');
        }

        return new JsonResponse($sheet);
    }
}
