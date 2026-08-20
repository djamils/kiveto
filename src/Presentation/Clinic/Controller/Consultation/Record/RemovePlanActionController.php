<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RemovePlanAction\RemovePlanAction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RemovePlanActionController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/plan-actions/remove', name: 'clinic_consultation_remove_plan_action', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RemovePlanAction => new RemovePlanAction(
                consultationId: $id,
                clinicId: $clinicId,
                planActionId: $request->request->getString('planActionId'),
            ),
        );
    }
}
