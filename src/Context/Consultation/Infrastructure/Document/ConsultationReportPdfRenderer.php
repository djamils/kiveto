<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Document;

use App\Context\Consultation\Application\Port\ClinicDocumentIdentityInterface;
use App\Context\Consultation\Application\Port\ConsultationReportPdfRendererInterface;
use App\Context\Consultation\Application\Port\PatientSnapshotProviderInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Application\Document\RenderedDocument;

/**
 * The consultation report through the shared document templates: the record
 * itself, the animal and its owner, and the practitioner who signs it.
 */
final readonly class ConsultationReportPdfRenderer implements ConsultationReportPdfRendererInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PatientSnapshotProviderInterface $patients,
        private PractitionerDirectoryInterface $practitioners,
        private ClinicDocumentIdentityInterface $clinics,
        private DocumentPdfRendererInterface $documents,
    ) {
    }

    public function report(string $clinicId, string $consultationId): RenderedDocument
    {
        $consultation = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));
        \assert($consultation instanceof ConsultationDetailsDTO);

        $snapshot  = $this->patients->snapshot($clinicId, $consultation->patientId);
        $patient   = $snapshot->patient->name;
        $owner     = $snapshot->owner?->fullName;
        $startedAt = new \DateTimeImmutable($consultation->startedAtUtc, new \DateTimeZone('UTC'));

        $pdf = $this->documents->render(
            'clinic/document/report.html.twig',
            [
                'consultation' => $consultation,
                'patient'      => $snapshot->patient,
                'owner'        => $snapshot->owner,
                'practitioner' => $this->practitioners->find($clinicId, $consultation->practitionerUserId),
                'startedAt'    => $startedAt,
                'closedAt'     => null !== $consultation->closedAtUtc
                    ? new \DateTimeImmutable($consultation->closedAtUtc, new \DateTimeZone('UTC'))
                    : null,
            ],
            new DocumentFooter(
                $this->clinics->forClinic($clinicId),
                'Compte rendu de consultation',
                null !== $owner ? \sprintf('%s / %s', $patient, $owner) : $patient,
            ),
        );

        return new RenderedDocument(\sprintf('compte-rendu-%s-%s.pdf', $patient, $startedAt->format('Y-m-d')), $pdf);
    }
}
