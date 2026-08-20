<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RecordExamSystem\RecordExamSystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RecordExamSystemController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/exam-systems', name: 'clinic_consultation_record_exam_system', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RecordExamSystem => new RecordExamSystem(
                consultationId: $id,
                clinicId: $clinicId,
                system: $request->request->getString('system'),
                status: $request->request->getString('status'),
                notes: $request->request->getString('notes'),
                structuredData: CockpitEndpoint::stringMap($request, 'structuredData'),
                recordedByUserId: $userId,
            ),
        );
    }
}
