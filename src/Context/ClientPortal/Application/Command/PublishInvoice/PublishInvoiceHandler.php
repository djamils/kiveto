<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\PublishInvoice;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\VisitPatientsInterface;
use App\Context\ClientPortal\Application\Service\DocumentPublisher;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads the invoice live: a draft, a cancelled invoice or a counter sale
 * without client is not published. The animal comes from the visit
 * (admission → patient → animal), the consultation when the visit has one.
 */
#[AsMessageHandler]
final readonly class PublishInvoiceHandler
{
    public function __construct(
        private InvoiceDocumentsInterface $invoices,
        private VisitPatientsInterface $visits,
        private ConsultationDocumentsInterface $consultations,
        private DocumentPublisher $publisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PublishInvoice $command): void
    {
        $invoice = $this->invoices->publication($command->clinicId, $command->invoiceId);

        if (null === $invoice) {
            return;
        }

        $patientId      = null;
        $petIds         = [];
        $consultationId = null;

        if (null !== $invoice->admissionId) {
            $patientId      = $this->visits->patientOfAdmission($command->clinicId, $invoice->admissionId);
            $animalId       = null === $patientId ? null : $this->visits->animalOfPatient($command->clinicId, $patientId);
            $petIds         = null === $animalId ? [] : [$animalId];
            $consultations  = $this->consultations->consultationsOfAdmission($command->clinicId, $invoice->admissionId);
            $consultationId = 1 === \count($consultations) ? $consultations[0] : null;
        }

        $this->publisher->insert(PublishedDocument::publish(
            id: PublishedDocumentId::fromString($this->uuids->generate()),
            clinicId: $command->clinicId,
            clientId: $invoice->payerClientId,
            type: DocumentType::INVOICE,
            sourceRef: $command->invoiceId,
            consultationId: $consultationId,
            patientId: $patientId,
            petIds: $petIds,
            title: 'Facture ' . $invoice->number,
            issuedAt: $invoice->issuedAt,
            practitionerName: null,
            linked: $this->publisher->hasConfirmedLink($command->clinicId, $invoice->payerClientId),
            now: $this->clock->now(),
        ));
    }
}
