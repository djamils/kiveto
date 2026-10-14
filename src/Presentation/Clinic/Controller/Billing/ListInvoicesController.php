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

/**
 * The register of invoices and credit notes (facturation-v-topbar_3). The
 * page module draws the table from the payload and asks
 * clinic_invoices_search for every other page or filter.
 */
#[Route(path: '/invoices', name: 'clinic_invoices', methods: ['GET'])]
final class ListInvoicesController extends AbstractController
{
    public function __construct(
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        return $this->render('clinic/billing/index.html.twig', [
            'payload' => $this->pages->page($currentClinicId->toString(), BillingRegisterFilters::fromRequest($request)),
        ]);
    }
}
