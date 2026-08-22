<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/invoices/{invoiceId}', name: 'clinic_invoice_details', methods: ['GET'])]
final class ShowInvoiceController extends AbstractController
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

        return $this->render('clinic/billing/detail/index.html.twig', [
            'invoice' => $invoice,
            'methods' => PaymentMethod::cases(),
        ]);
    }
}
