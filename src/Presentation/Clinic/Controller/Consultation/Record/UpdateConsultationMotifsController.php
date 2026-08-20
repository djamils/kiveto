<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateConsultationMotifsController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/motifs', name: 'clinic_consultation_update_motifs', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): UpdateConsultationMotifs => new UpdateConsultationMotifs(
                consultationId: $id,
                clinicId: $clinicId,
                labels: CockpitEndpoint::stringList($request, 'labels'),
            ),
        );
    }
}
