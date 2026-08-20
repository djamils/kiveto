<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\MarkAllSystemsNormal\MarkAllSystemsNormal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MarkAllSystemsNormalController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/exam-systems/all-normal', name: 'clinic_consultation_mark_all_systems_normal', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): MarkAllSystemsNormal => new MarkAllSystemsNormal(
                consultationId: $id,
                clinicId: $clinicId,
                systems: CockpitEndpoint::stringList($request, 'systems'),
                recordedByUserId: $userId,
            ),
        );
    }
}
