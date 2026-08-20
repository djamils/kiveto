<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RemovePrescriptionLine\RemovePrescriptionLine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RemovePrescriptionLineController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/prescription-lines/remove', name: 'clinic_consultation_remove_prescription_line', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RemovePrescriptionLine => new RemovePrescriptionLine(
                consultationId: $id,
                clinicId: $clinicId,
                prescriptionLineId: $request->request->getString('prescriptionLineId'),
            ),
        );
    }
}
