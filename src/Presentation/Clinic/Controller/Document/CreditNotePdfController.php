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
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(
    path: '/credit-notes/{creditNoteId}/pdf',
    name: 'clinic_credit_note_pdf',
    requirements: ['creditNoteId' => Requirement::UUID],
    methods: ['GET'],
)]
final class CreditNotePdfController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly InvoicePdfRendererInterface $invoices,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $creditNoteId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $document = $this->invoices->creditNote($currentClinicId->toString(), $creditNoteId);
        } catch (\DomainException) {
            throw $this->createNotFoundException('Avoir introuvable.');
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        return $this->documents->response($document->content, $document->filename);
    }
}
