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
 * One invoice: the register with its sheet open, over the whole register
 * (« Tout ») so that its row is there behind it — the mock-up's `?facture=`
 * entry point. Every link to an invoice and every act done on one comes back
 * here.
 */
#[Route(
    path: '/invoices/{invoiceId}',
    name: 'clinic_invoice_details',
    requirements: ['invoiceId' => Requirement::UUID],
    methods: ['GET'],
)]
final class ShowInvoiceController extends AbstractController
{
    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $invoiceId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $sheet    = $this->pages->invoiceSheet($clinicId, $invoiceId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('clinic/billing/index.html.twig', [
            'payload' => $this->pages->page(
                $clinicId,
                BillingRegisterFilters::fromRequest($request, $request->query->has('per') ? null : 'tout'),
                $sheet,
                $request->query->getBoolean('encaisser'),
                $request->query->getBoolean('imprimer'),
            ),
        ]);
    }
}
