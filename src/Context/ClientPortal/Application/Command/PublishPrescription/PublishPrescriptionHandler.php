<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\PublishPrescription;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Service\DocumentPublisher;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads the prescription live: a draft, a cancelled one or one without
 * owner client is not published. The prescriber's name is kept as the
 * document's practitioner, even if they later leave the clinic.
 */
#[AsMessageHandler]
final readonly class PublishPrescriptionHandler
{
    public function __construct(
        private PrescriptionDocumentsInterface $prescriptions,
        private ConsultationDocumentsInterface $consultations,
        private DocumentPublisher $publisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PublishPrescription $command): void
    {
        $prescription = $this->prescriptions->publication($command->clinicId, $command->prescriptionId);

        if (null === $prescription) {
            return;
        }

        $patientId = $this->consultations->shareability($command->clinicId, $prescription->consultationId)?->patientId;

        $this->publisher->insert(PublishedDocument::publish(
            id: PublishedDocumentId::fromString($this->uuids->generate()),
            clinicId: $command->clinicId,
            clientId: $prescription->ownerClientId,
            type: DocumentType::PRESCRIPTION,
            sourceRef: $command->prescriptionId,
            consultationId: $prescription->consultationId,
            patientId: $patientId,
            petIds: null === $prescription->animalId ? [] : [$prescription->animalId],
            title: 'Ordonnance ' . $prescription->number,
            issuedAt: $prescription->issuedAt,
            practitionerName: $prescription->prescriberName,
            linked: $this->publisher->hasConfirmedLink($command->clinicId, $prescription->ownerClientId),
            now: $this->clock->now(),
        ));
    }
}
