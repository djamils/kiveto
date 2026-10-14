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
 * The side sheet of one invoice, as JSON: a click on a row of the register
 * opens it without leaving the page.
 */
#[Route(
    path: '/invoices/{invoiceId}/sheet',
    name: 'clinic_invoice_sheet',
    requirements: ['invoiceId' => Requirement::UUID],
    methods: ['GET'],
)]
final class InvoiceSheetController extends AbstractController
{
    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $invoiceId): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $sheet = $this->pages->invoiceSheet($currentClinicId->toString(), $invoiceId);

        if (null === $sheet) {
            return new JsonResponse(['error' => 'Facture introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($sheet);
    }
}
