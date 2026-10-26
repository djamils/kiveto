<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Prescription\Application\Port\PrescriptionPdfRendererInterface;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Application\Document\DocumentRenderingFailed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Everything the client leaves with, in one file: the report, then the
 * ordonnances signed during the visit, then the invoice — each exactly as
 * printed on its own.
 */
#[Route(
    path: '/consultations/{consultationId}/documents.pdf',
    name: 'clinic_consultation_bundle_pdf',
    methods: ['GET'],
)]
final class ConsultationBundlePdfController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ConsultationReportPdfRendererInterface $reports,
        private readonly PrescriptionPdfRendererInterface $prescriptions,
        private readonly InvoicePdfRendererInterface $invoices,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $consultationId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $consultation = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));
            \assert($consultation instanceof ConsultationDetailsDTO);
            $report = $this->reports->report($clinicId, $consultationId);
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('Consultation introuvable.', $exception);
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        try {
            $parts = [$report->content];

            foreach ($this->prescriptionIds($clinicId, $consultationId) as $prescriptionId) {
                $parts[] = $this->prescriptions->prescription($clinicId, $prescriptionId)->content;
            }

            $invoiceId = $this->invoiceId($clinicId, $consultation->admissionId);

            if (null !== $invoiceId) {
                $parts[] = $this->invoices->invoice($clinicId, $invoiceId)->content;
            }
        } catch (DocumentRenderingFailed $exception) {
            throw DocumentPdfFactory::unavailable($exception);
        }

        return $this->documents->response($this->documents->merge($parts), 'dossier-' . $report->filename);
    }

    /**
     * The printable ordonnances of the visit.
     *
     * @return list<string>
     */
    private function prescriptionIds(string $clinicId, string $consultationId): array
    {
        $rows = $this->queryBus->ask(new ListPrescriptionsForConsultation(
            clinicId: $clinicId,
            consultationId: $consultationId,
        ));

        if (!\is_array($rows)) {
            return [];
        }

        $ids = [];

        foreach ($rows as $row) {
            if ($row instanceof ConsultationPrescriptionRow && $row->isPrintable()) {
                $ids[] = $row->id;
            }
        }

        return $ids;
    }

    private function invoiceId(string $clinicId, string $admissionId): ?string
    {
        $row = $this->queryBus->ask(new FindInvoiceForAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
        ));

        return $row instanceof AdmissionInvoiceRow && $row->isPrintable() ? $row->id : null;
    }
}
