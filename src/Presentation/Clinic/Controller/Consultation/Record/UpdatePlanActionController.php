<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\UpdatePlanAction\UpdatePlanAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdatePlanActionController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/plan-actions/update', name: 'clinic_consultation_update_plan_action', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): UpdatePlanAction => new UpdatePlanAction(
                consultationId: $id,
                clinicId: $clinicId,
                planActionId: $request->request->getString('planActionId'),
                description: $request->request->getString('description'),
                posology: $request->request->getString('posology'),
                durationDays: CockpitEndpoint::optionalPositiveInt($request, 'durationDays'),
                followUpDays: CockpitEndpoint::optionalPositiveInt($request, 'followUpDays'),
                quantity: CockpitEndpoint::quantity($request),
            ),
        );
    }
}
