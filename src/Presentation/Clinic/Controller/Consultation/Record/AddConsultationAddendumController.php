<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\AddConsultationAddendum\AddConsultationAddendum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signs a dated addendum onto a closed consultation (« Ajouter un avenant »).
 */
final class AddConsultationAddendumController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/addenda', name: 'clinic_consultation_add_addendum', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): AddConsultationAddendum => new AddConsultationAddendum(
                consultationId: $id,
                clinicId: $clinicId,
                content: $request->request->getString('content'),
                createdByUserId: $userId,
            ),
        );
    }
}
