<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalPrescription;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/** Only through a listable registry row of the account: issued alone is not enough. */
#[AsMessageHandler]
final readonly class GetPortalPrescriptionHandler
{
    public function __construct(
        private PublishedDocumentReadModelInterface $documents,
        private PrescriptionDocumentsInterface $prescriptions,
        private ClinicProfileInterface $clinics,
    ) {
    }

    public function __invoke(GetPortalPrescription $query): ?PortalPrescription
    {
        if (!Uuid::isValid($query->prescriptionId)) {
            return null;
        }

        $document = $this->documents->findBySource($query->userId, $query->clinicId, DocumentType::PRESCRIPTION->value, $query->prescriptionId);
        $content  = null === $document ? null : $this->prescriptions->content($query->clinicId, $query->prescriptionId);

        if (null === $content) {
            return null;
        }

        return new PortalPrescription(
            $content->prescriptionId,
            $content->issuedAt->setTimezone(new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone)),
            $content->prescriberName,
            $content->animalId,
            $content->patientName,
            $content->lines,
        );
    }
}
