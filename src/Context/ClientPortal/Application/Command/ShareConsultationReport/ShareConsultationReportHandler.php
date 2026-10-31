<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ShareConsultationReport;

use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Service\DocumentPublisher;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Exception\ReportNotShareableException;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The first share publishes the report (version 1); a later share (after
 * an addendum, or for a new primary owner) requests a new version while the
 * previous one keeps being served. The row is locked, so two clicks make
 * one version. Never blocked by an owner without Portal account: the
 * report then waits for their link.
 */
#[AsMessageHandler]
final readonly class ShareConsultationReportHandler
{
    public function __construct(
        private ConsultationDocumentsInterface $consultations,
        private PublishedDocumentRepositoryInterface $documents,
        private ClinicProfileInterface $clinics,
        private DocumentPublisher $publisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ShareConsultationReport $command): ReportShareResult
    {
        $consultation = $this->consultations->shareability($command->clinicId, $command->consultationId)
            ?? throw new ReportNotShareableException(ReportNotShareableException::UNKNOWN);

        if (null !== $consultation->refusal || null === $consultation->animalId || null === $consultation->primaryOwnerClientId || null === $consultation->closedAt) {
            throw new ReportNotShareableException($consultation->refusal ?? ReportNotShareableException::NOT_CLOSED);
        }

        $ownerId = $consultation->primaryOwnerClientId;
        $linked  = $this->publisher->hasConfirmedLink($command->clinicId, $ownerId);
        $now     = $this->clock->now();
        $shared  = $this->documents->findForUpdate($command->clinicId, DocumentType::REPORT, $command->consultationId);

        if (null === $shared) {
            $document = PublishedDocument::publish(
                id: PublishedDocumentId::fromString($this->uuids->generate()),
                clinicId: $command->clinicId,
                clientId: $ownerId,
                type: DocumentType::REPORT,
                sourceRef: $command->consultationId,
                consultationId: $command->consultationId,
                patientId: $consultation->patientId,
                petIds: [$consultation->animalId],
                title: 'Compte rendu du ' . $this->localDate($command->clinicId, $consultation->startedAt),
                issuedAt: $consultation->closedAt,
                practitionerName: $consultation->practitionerName,
                linked: $linked,
                now: $now,
            );

            if ($this->publisher->insert($document)) {
                return new ReportShareResult($document->id()->toString(), $linked);
            }

            // A concurrent first share won the insert: share again on its row.
            $shared = $this->documents->findForUpdate($command->clinicId, DocumentType::REPORT, $command->consultationId)
                ?? throw new \LogicException('The shared report row vanished.');
        }

        $version = $shared->reshare($ownerId, $consultation->practitionerName, $linked, $now);
        $shared->assignPets([$consultation->animalId], $now);
        $this->publisher->save($shared, $version);

        return new ReportShareResult($shared->id()->toString(), $linked);
    }

    private function localDate(string $clinicId, \DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone))->format('d/m/Y');
    }
}
