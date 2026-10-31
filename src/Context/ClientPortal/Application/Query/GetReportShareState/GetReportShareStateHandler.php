<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetReportShareState;

use App\Context\ClientPortal\Application\Port\ConsultationDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Same checks as ShareConsultationReport, so the button is disabled for
 * exactly the shares the endpoint refuses.
 */
#[AsMessageHandler]
final readonly class GetReportShareStateHandler
{
    public function __construct(
        private ConsultationDocumentsInterface $consultations,
        private PublishedDocumentReadModelInterface $documents,
    ) {
    }

    public function __invoke(GetReportShareState $query): ReportShareState
    {
        $consultation = $this->consultations->shareability($query->clinicId, $query->consultationId);

        if (null === $consultation) {
            return new ReportShareState(false, ReportShareState::UNKNOWN, null);
        }

        $refused = null !== $consultation->refusal || null === $consultation->animalId
            || null === $consultation->primaryOwnerClientId || null === $consultation->closedAt;
        $reason = $refused ? ($consultation->refusal ?? ReportShareState::NOT_CLOSED) : null;

        return new ReportShareState(
            shareable: null === $reason,
            reason: $reason,
            sharedAt: $this->documents->reportSharedAt($query->clinicId, $query->consultationId),
        );
    }
}
