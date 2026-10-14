<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Presentation\Clinic\ViewModel\Billing\BillingPageBuilder;
use App\Presentation\Clinic\ViewModel\Billing\BillingRegisterFilters;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The checkout of a visit — where « Encaisser » leads from the flux and the
 * dashboard.
 *
 * Opening it prepares the invoice — the draft is (re)built from what the
 * practitioner recorded — so the desk always sees the current state of the
 * visit rather than a snapshot taken at some earlier moment. It is the
 * register with that invoice's sheet open and its « Encaisser » window up.
 */
#[Route(path: '/checkout/{admissionId}', name: 'clinic_checkout', methods: ['GET'])]
final class ShowCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly BillingPageBuilder $pages,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $admissionId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission(
                clinicId: $clinicId,
                admissionId: $admissionId,
            ));
        } catch (\DomainException) {
            $this->addFlash('error', 'La visite n\'a pas pu être préparée à l\'encaissement.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        \assert(\is_string($invoiceId));

        $sheet = $this->pages->invoiceSheet($clinicId, $invoiceId);

        if (null === $sheet) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        return $this->render('clinic/billing/index.html.twig', [
            'payload' => $this->pages->page($clinicId, BillingRegisterFilters::fromRequest($request, 'tout'), $sheet, true),
        ]);
    }
}
