<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AddPrescriptionLineController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/prescription-lines/add', name: 'clinic_consultation_add_prescription_line', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): AddPrescriptionLine => new AddPrescriptionLine(
                consultationId: $id,
                clinicId: $clinicId,
                articleId: $request->request->getString('articleId'),
                dose: $request->request->getString('dose'),
                frequency: $request->request->getString('frequency'),
                durationDays: CockpitEndpoint::optionalPositiveInt($request, 'durationDays'),
                route: $request->request->getString('route'),
                quantity: CockpitEndpoint::quantity($request),
                createdByUserId: $userId,
            ),
        );
    }
}
