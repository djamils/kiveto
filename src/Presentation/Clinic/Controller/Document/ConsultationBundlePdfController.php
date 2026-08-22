<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Presentation\Clinic\ViewModel\Document\ConsultationReportContext;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Everything the client leaves with, in one file: the report, then the
 * ordonnances signed during the visit, then the invoice.
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
        private readonly ConsultationReportContext $reportContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $consultationId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $report = $this->reportContext->build($consultationId, $clinicId);
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('Consultation introuvable.', $exception);
        }

        $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $parts = [$this->documents->render(
            'clinic/document/report.html.twig',
            $report['context'],
            $clinic,
            'Compte rendu de consultation',
            $report['subject'],
        )];

        foreach ($this->prescriptions($clinicId, $consultationId) as $prescription) {
            $parts[] = $this->documents->render(
                'clinic/document/prescription.html.twig',
                ['prescription' => $prescription],
                $clinic,
                \sprintf('Ordonnance · %s', $prescription->number ?? 'brouillon'),
                $report['subject'],
            );
        }

        $invoice = $this->invoice($clinicId, $report['consultation']->admissionId);

        if (null !== $invoice) {
            $parts[] = $this->documents->render(
                'clinic/document/invoice.html.twig',
                ['invoice' => $invoice],
                $clinic,
                \sprintf('Facture · %s', $invoice->number ?? 'brouillon'),
                $invoice->payerName,
            );
        }

        return $this->documents->response(
            $this->documents->merge($parts),
            \sprintf('dossier-%s', $report['filename']),
        );
    }

    /**
     * @return list<PrescriptionDetails>
     */
    private function prescriptions(string $clinicId, string $consultationId): array
    {
        $rows = $this->queryBus->ask(new ListPrescriptionsForConsultation(
            clinicId: $clinicId,
            consultationId: $consultationId,
        ));

        if (!\is_array($rows)) {
            return [];
        }

        $prescriptions = [];

        foreach ($rows as $row) {
            if (!$row instanceof ConsultationPrescriptionRow || !$row->isPrintable()) {
                continue;
            }

            $details = $this->queryBus->ask(new GetPrescriptionDetails(
                clinicId: $clinicId,
                prescriptionId: $row->id,
            ));

            if ($details instanceof PrescriptionDetails) {
                $prescriptions[] = $details;
            }
        }

        return $prescriptions;
    }

    private function invoice(string $clinicId, string $admissionId): ?InvoiceDetails
    {
        $row = $this->queryBus->ask(new FindInvoiceForAdmission(
            clinicId: $clinicId,
            admissionId: $admissionId,
        ));

        if (!$row instanceof AdmissionInvoiceRow || !$row->isPrintable()) {
            return null;
        }

        $details = $this->queryBus->ask(new GetInvoiceDetails(
            clinicId: $clinicId,
            invoiceId: $row->id,
        ));

        return $details instanceof InvoiceDetails ? $details : null;
    }
}
