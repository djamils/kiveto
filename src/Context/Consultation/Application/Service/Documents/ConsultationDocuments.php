<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service\Documents;

use App\Context\Consultation\Application\Port\ClientContactInterface;
use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\InvoiceDocumentInterface;
use App\Context\Consultation\Application\Port\PatientSnapshotProviderInterface;
use App\Context\Consultation\Application\Port\PrescriptionDocumentsInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;

/**
 * Checks a documents e-mail request (AC 27): the consultation is closed, its
 * owner has an e-mail, and every ticked document belongs to it — the
 * report, one of its signed ordonnances, the issued invoice of its visit.
 * Run when the user clicks and again by the worker, which sends nothing
 * when the answer has changed in between.
 */
final readonly class ConsultationDocuments
{
    public const string REPORT              = 'report';
    public const string INVOICE             = 'invoice';
    public const string PRESCRIPTION_PREFIX = 'prescription:';

    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
        private PatientSnapshotProviderInterface $patients,
        private ClientContactInterface $contacts,
        private PrescriptionDocumentsInterface $prescriptions,
        private InvoiceDocumentInterface $invoices,
    ) {
    }

    /**
     * @param list<string> $documents `report`, `prescription:<id>`, `invoice`
     *
     * @throws ConsultationDocumentsNotSendable
     * @throws \DomainException                 when the consultation is unknown to this clinic
     */
    public function select(string $clinicId, string $consultationId, array $documents): DocumentSelection
    {
        $documents = array_values(array_unique($documents));

        if ([] === $documents) {
            throw ConsultationDocumentsNotSendable::nothingSelected();
        }

        $consultation = $this->consultations->findById(ConsultationId::fromString($consultationId), ClinicId::fromString($clinicId));

        if (!$consultation->isClosed()) {
            throw ConsultationDocumentsNotSendable::notClosed();
        }

        $snapshot = $this->patients->snapshot($clinicId, $consultation->patientId);
        $owner    = null !== $snapshot->owner ? $this->contacts->contactOf($clinicId, $snapshot->owner->clientId) : null;

        if (null === $owner || !$owner->canReceiveDocuments()) {
            throw ConsultationDocumentsNotSendable::noEmail();
        }

        $wanted = array_map(
            static fn (string $document): string => str_starts_with($document, self::PRESCRIPTION_PREFIX) ? mb_substr($document, mb_strlen(self::PRESCRIPTION_PREFIX)) : '',
            array_values(array_filter($documents, static fn (string $document): bool => !\in_array($document, [self::REPORT, self::INVOICE], true))),
        );
        $printable = [] !== $wanted ? $this->prescriptions->printable($clinicId, $consultationId) : [];

        if ([] !== array_diff($wanted, array_keys($printable))) {
            throw ConsultationDocumentsNotSendable::foreignDocument();
        }

        $invoice = null;

        if (\in_array(self::INVOICE, $documents, true)) {
            $invoice = $this->invoices->invoiceOf($clinicId, $consultation->admissionId) ?? throw ConsultationDocumentsNotSendable::foreignDocument();
        }

        return new DocumentSelection(
            consultation: $consultation,
            animalName: $snapshot->patient->name,
            owner: $owner,
            report: \in_array(self::REPORT, $documents, true),
            prescriptions: array_intersect_key($printable, array_flip($wanted)),
            invoice: $invoice,
        );
    }
}
