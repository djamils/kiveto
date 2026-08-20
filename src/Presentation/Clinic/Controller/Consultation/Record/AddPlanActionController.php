<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AddPlanActionController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/plan-actions/add', name: 'clinic_consultation_add_plan_action', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): AddPlanAction => new AddPlanAction(
                consultationId: $id,
                clinicId: $clinicId,
                kind: $request->request->getString('kind'),
                description: $request->request->getString('description'),
                catalogItemId: '' !== $request->request->getString('catalogItemId')
                    ? $request->request->getString('catalogItemId')
                    : null,
                catalogCode: $request->request->getString('catalogCode'),
                posology: $request->request->getString('posology'),
                durationDays: CockpitEndpoint::optionalPositiveInt($request, 'durationDays'),
                followUpDays: CockpitEndpoint::optionalPositiveInt($request, 'followUpDays'),
                quantity: CockpitEndpoint::quantity($request),
                createdByUserId: $userId,
            ),
        );
    }
}
