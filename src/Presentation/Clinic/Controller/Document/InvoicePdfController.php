<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/invoices/{invoiceId}/pdf', name: 'clinic_invoice_pdf', methods: ['GET'])]
final class InvoicePdfController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly InvoicePdfRendererInterface $invoices,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $invoiceId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $document = $this->invoices->invoice($currentClinicId->toString(), $invoiceId);
        } catch (\DomainException) {
            throw $this->createNotFoundException('Facture introuvable.');
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        return $this->documents->response($document->content, $document->filename);
    }
}
