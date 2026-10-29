<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Document;

use App\Context\Prescription\Application\Port\ClinicDocumentIdentityInterface;
use App\Context\Prescription\Application\Port\PrescriptionPdfRendererInterface;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;

/**
 * The ordonnance through the shared document templates.
 */
final readonly class PrescriptionPdfRenderer implements PrescriptionPdfRendererInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private ClinicDocumentIdentityInterface $clinics,
        private DocumentPdfRendererInterface $documents,
    ) {
    }

    public function prescription(string $clinicId, string $prescriptionId): RenderedDocument
    {
        $prescription = $this->queryBus->ask(new GetPrescriptionDetails(
            clinicId: $clinicId,
            prescriptionId: $prescriptionId,
        ));
        \assert($prescription instanceof PrescriptionDetails);

        $pdf = $this->documents->render(
            'clinic/document/prescription.html.twig',
            ['prescription' => $prescription],
            new DocumentFooter(
                $this->clinics->forClinic($clinicId),
                \sprintf('Ordonnance · %s', $prescription->number ?? 'brouillon'),
                \sprintf('%s / %s', $prescription->patientName, $prescription->ownerName),
            ),
        );

        return new RenderedDocument(($prescription->number ?? 'ordonnance') . '.pdf', $pdf);
    }
}
