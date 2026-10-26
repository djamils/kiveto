<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/consultations/{consultationId}/report.pdf',
    name: 'clinic_consultation_report_pdf',
    methods: ['GET'],
)]
final class ConsultationReportPdfController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ConsultationReportPdfRendererInterface $reports,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $consultationId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $document = $this->reports->report($currentClinicId->toString(), $consultationId);
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('Consultation introuvable.', $exception);
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        return $this->documents->response($document->content, $document->filename);
    }
}
