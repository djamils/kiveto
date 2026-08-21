<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\SetPrimaryDiagnosis\SetPrimaryDiagnosis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SetPrimaryDiagnosisController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/diagnoses/primary', name: 'clinic_consultation_set_primary_diagnosis', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): SetPrimaryDiagnosis => new SetPrimaryDiagnosis(
                consultationId: $id,
                clinicId: $clinicId,
                diagnosisId: '' !== $request->request->getString('diagnosisId')
                    ? $request->request->getString('diagnosisId')
                    : null,
            ),
        );
    }
}
