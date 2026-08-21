<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AddDiagnosisController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/diagnoses/add', name: 'clinic_consultation_add_diagnosis', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): AddDiagnosis => new AddDiagnosis(
                consultationId: $id,
                clinicId: $clinicId,
                code: $request->request->getString('code'),
                label: $request->request->getString('label'),
                certainty: $request->request->getString('certainty'),
                note: $request->request->getString('note'),
                isPrimary: $request->request->getBoolean('isPrimary'),
                source: $request->request->getString('source', 'MANUAL'),
                createdByUserId: $userId,
            ),
        );
    }
}
