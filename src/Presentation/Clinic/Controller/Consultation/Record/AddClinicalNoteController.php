<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\AddClinicalNote\AddClinicalNote;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publishes a note for the team from the cockpit's « Notes » drawer.
 */
final class AddClinicalNoteController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/notes', name: 'clinic_consultation_add_note', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): AddClinicalNote => new AddClinicalNote(
                consultationId: $id,
                clinicId: $clinicId,
                noteType: $request->request->getString('noteType', 'GENERAL'),
                content: $request->request->getString('content'),
                createdByUserId: $userId,
            ),
        );
    }
}
