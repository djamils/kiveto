<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Prescription\Application\Port\PrescriptionPdfRendererInterface;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/prescriptions/{prescriptionId}/pdf', name: 'clinic_prescription_pdf', methods: ['GET'])]
final class PrescriptionPdfController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly PrescriptionPdfRendererInterface $prescriptions,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $prescriptionId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $document = $this->prescriptions->prescription($currentClinicId->toString(), $prescriptionId);
        } catch (\DomainException) {
            throw $this->createNotFoundException('Ordonnance introuvable.');
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        return $this->documents->response($document->content, $document->filename);
    }
}
