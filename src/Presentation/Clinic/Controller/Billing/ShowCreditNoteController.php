<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Presentation\Clinic\ViewModel\Billing\BillingRegisterFilters;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * One credit note: the register with its sheet open, over « Tout ».
 */
#[Route(
    path: '/credit-notes/{creditNoteId}',
    name: 'clinic_credit_note_details',
    requirements: ['creditNoteId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ShowCreditNoteController extends AbstractController
{
    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $creditNoteId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $sheet    = $this->pages->creditNoteSheet($clinicId, $creditNoteId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Avoir introuvable.');
        }

        return $this->render('clinic/billing/index.html.twig', [
            'payload' => $this->pages->page(
                $clinicId,
                BillingRegisterFilters::fromRequest($request, $request->query->has('per') ? null : 'tout'),
                $sheet,
            ),
        ]);
    }
}
