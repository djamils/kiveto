<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The side sheet of one credit note, as JSON.
 */
#[Route(
    path: '/credit-notes/{creditNoteId}/sheet',
    name: 'clinic_credit_note_sheet',
    requirements: ['creditNoteId' => Requirement::UUID],
    methods: ['GET'],
)]
final class CreditNoteSheetController extends AbstractController
{
    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $creditNoteId): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $sheet = $this->pages->creditNoteSheet($currentClinicId->toString(), $creditNoteId);

        if (null === $sheet) {
            return new JsonResponse(['error' => 'Avoir introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($sheet);
    }
}
