<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RecordChiefComplaintController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route(
        '/clinic/consultations/{id}/chief-complaint',
        name: 'clinic_consultation_record_chief_complaint',
        methods: ['POST'],
    )]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RecordChiefComplaint => new RecordChiefComplaint(
                consultationId: $id,
                clinicId: $clinicId,
                chiefComplaint: $request->request->getString('chiefComplaint'),
            ),
        );
    }
}
