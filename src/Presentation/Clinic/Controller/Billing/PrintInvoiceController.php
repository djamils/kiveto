<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/invoices/{invoiceId}/print', name: 'clinic_invoice_print', methods: ['GET'])]
final class PrintInvoiceController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $invoiceId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $invoice = $this->queryBus->ask(new GetInvoiceDetails(
                clinicId: $currentClinicId->toString(),
                invoiceId: $invoiceId,
            ));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Facture introuvable.');
        }

        \assert($invoice instanceof InvoiceDetails);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));

        return $this->render('clinic/billing/print/index.html.twig', [
            'invoice' => $invoice,
            'clinic'  => $clinic instanceof ClinicDto ? $clinic : null,
        ]);
    }
}
