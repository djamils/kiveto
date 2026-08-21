<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\UpdateDiagnosis\UpdateDiagnosis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateDiagnosisController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/diagnoses/update', name: 'clinic_consultation_update_diagnosis', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): UpdateDiagnosis => new UpdateDiagnosis(
                consultationId: $id,
                clinicId: $clinicId,
                diagnosisId: $request->request->getString('diagnosisId'),
                code: $request->request->getString('code'),
                label: $request->request->getString('label'),
                certainty: $request->request->getString('certainty'),
                note: $request->request->getString('note'),
            ),
        );
    }
}
